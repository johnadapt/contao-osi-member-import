<?php /** @var array $this->rows */ ?>

<style>
.member-import-preview .status-import { background-color: #dff0d8; } /* green */
.member-import-preview .status-skip   { background-color: #f5f5f5; } /* gray */
.member-import-preview .status-fail   { background-color: #f2dede; } /* red */
</style>

<table class="tl_listing member-import-preview">
    <thead>
        <tr>
            <th>Email</th>
            <th>Name</th>
            <th>Groups</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($this->rows as $row): ?>
        <?php
            $status = $row['status'];
            $classes = '';
            $label   = '';

            switch ($status) {
                case 'import':
                    $classes = 'status-import';
                    $label   = 'Will Import';
                    break;
                case 'skip':
                    $classes = 'status-skip';
                    $label   = 'Skipped (duplicate)';
                    break;
                case 'fail':
                    $classes = 'status-fail';
                    $label   = 'Failed (missing data)';
                    break;
            }
        ?>
        <tr class="<?= $classes ?>">
            <td><?= $row['data']['email'] ?></td>
            <td><?= $row['data']['firstname'] ?> <?= $row['data']['lastname'] ?></td>
            <td><?= implode(', ', (array) $row['data']['groups']) ?></td>
            <td><strong><?= $label ?></strong></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
