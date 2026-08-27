<?php
$news = is_array($news ?? null) ? $news : [];
$newsFormMode = 'edit';
require __DIR__ . '/_form.php';
