<?php

namespace BrightCloudStudio\MemberImport;

use Contao\BackendModule;
use Contao\BackendTemplate;
use Contao\Environment;
use Contao\Input;
use Contao\MemberGroupModel;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\Config;
use Contao\System;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Csrf\CsrfToken;

class MemberImport extends BackendModule
{
    protected $strTemplate = 'be_member_import';

    protected function compile(): void
    {
        $this->Template->message = Message::generate();
        $this->Template->report = null;
        $this->Template->emailSubject = Config::get('bcs_member_import_email_subject') ?: 'Reset your account password';
        $this->Template->emailBody = Config::get('bcs_member_import_email_body') ?: "Hi {{firstname}},\n\nPlease set your password using this link:\n\n{{reset_link}}\n\nThis link will expire soon.";

        if (Input::post('bcs_import') !== null) {
            if (!$this->isValidCsrf((string) Input::post('_token'))) {
                Message::addError('Invalid request token.');
                $this->Template->message = Message::generate();
                return;
            }
            $this->Template->report = $this->handleUploadAndImport();
            $this->Template->message = Message::generate();
        }

        if (Input::post('bcs_send_resets')) {
            if (!$this->isValidCsrf((string) Input::post('_token'))) {
                Message::addError('Invalid request token.');
                $this->Template->message = Message::generate();
                return;
            }

            $ids     = (array) Input::post('member_ids');
            $subject = (string) Input::post('email_subject');
            $body    = (string) Input::post('email_body');
            $saveDef = (bool)   Input::post('save_defaults');

            if ($saveDef) {
                Config::persist('bcs_member_import_email_subject', $subject);
                Config::persist('bcs_member_import_email_body', $body);
            }

            $this->sendPasswordResets($ids, $subject, $body);
            $this->Template->message = Message::generate();
        }
    }

    private function isValidCsrf(string $submitted): bool
    {
        $mgr = System::getContainer()->get('contao.csrf.token_manager');
        return $mgr->isTokenValid(new CsrfToken('contao_backend', $submitted));
    }

    protected function handleUploadAndImport(): array
    {
        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            Message::addError('Please choose a CSV file.');
            return ['total'=>0,'created'=>0,'groupsCreated'=>0,'failures'=>[]];
        }

        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$fh) {
            Message::addError('Unable to read CSV file.');
            return ['total'=>0,'created'=>0,'groupsCreated'=>0,'failures'=>[]];
        }

        $headers = fgetcsv($fh);
        if (!$headers) {
            fclose($fh);
            Message::addError('CSV missing header row.');
            return ['total'=>0,'created'=>0,'groupsCreated'=>0,'failures'=>[]];
        }

        $headers = array_map('trim', $headers);
        $index = array_flip($headers);
        $required = ['firstname','lastname','email','username','member_group'];
        foreach ($required as $col) {
            if (!isset($index[$col])) {
                fclose($fh);
                Message::addError('Missing required header: '.$col);
                return ['total'=>0,'created'=>0,'groupsCreated'=>0,'failures'=>[]];
            }
        }

        $total=0;$created=0;$groupsCreated=0;$failures=[];$newlyCreated=[];$groupCache=[];
        $rowNum=1;
        while(($row=fgetcsv($fh))!==false){
            $rowNum++;$total++;
            $data=[];
            foreach($headers as $i=>$name){$data[$name]=$row[$i]??'';}

            try{
                $email=$data['email']??'';
                $user=$data['username']??'';
                $label=$data['member_group']??'';
                if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Invalid email.');
                if($user==='') throw new \RuntimeException('Missing username.');
                if($label==='') throw new \RuntimeException('Missing member_group.');

                $groupId=$groupCache[$label]??null;
                if(!$groupId){
                    $existing=MemberGroupModel::findOneBy('name',$label);
                    if($existing){$groupId=$existing->id;}
                    else{$g=new MemberGroupModel();$g->tstamp=time();$g->name=$label;$g->save();$groupId=$g->id;$groupsCreated++;}
                    $groupCache[$label]=$groupId;
                }

                if(MemberModel::findOneBy('email',$email)) throw new \RuntimeException('Member exists.');

                $m=new MemberModel();
                $m->tstamp=time();
                $m->dateAdded=time();
                $m->firstname=$data['firstname']??'';
                $m->lastname=$data['lastname']??'';
                $m->email=$email;
                $m->username=$user;
                $m->password=password_hash($data['password']?:bin2hex(random_bytes(6)), PASSWORD_DEFAULT);
                $m->login=1;
                $m->disable='';
                $m->groups=StringUtil::serialize([$groupId]);
                $m->save();
                $created++;
                $newlyCreated[]=['id'=>$m->id,'firstname'=>$m->firstname,'lastname'=>$m->lastname,'email'=>$m->email,'username'=>$m->username,'member_group'=>$label];
            }catch(\Throwable $e){
                $failures[]=['row'=>$rowNum,'email'=>$data['email']??'','reason'=>$e->getMessage()];
            }
        }
        fclose($fh);
        Message::addConfirmation("Processed $total rows. Created $created members. $groupsCreated groups created.");
        return ['total'=>$total,'created'=>$created,'groupsCreated'=>$groupsCreated,'failures'=>$failures,'newlyCreated'=>$newlyCreated];
    }

    protected function sendPasswordResets(array $memberIds,string $subject,string $body): void
    {
        $memberIds=array_map('intval',$memberIds);
        $mailer=System::getContainer()->get('mailer');
        $siteUrl=Environment::get('url');
        $resetPage='/reset-password.html';

        $sent=0;
        foreach($memberIds as $id){
            $m=MemberModel::findByPk($id);
            if(!$m||!$m->email) continue;
            $token=bin2hex(random_bytes(16));
            $m->activation=$token;
            $m->save();
            $resetLink=$siteUrl.$resetPage.'?token='.$token;
            $rendered=strtr($body,[
                '{{firstname}}'=>$m->firstname,
                '{{lastname}}'=>$m->lastname,
                '{{email}}'=>$m->email,
                '{{username}}'=>$m->username,
                '{{reset_link}}'=>$resetLink
            ]);
            $email=(new Email())->to($m->email)->subject($subject)->text($rendered);
            try{$mailer->send($email);$sent++;}catch(\Throwable $e){Message::addError('Mail fail to '.$m->email);}        }
        Message::addConfirmation("Sent $sent reset email(s).");
    }
}
