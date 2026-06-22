<?php
// Legacy redirect: burning_issues.php has been renamed to news_room.php
// This file is kept as a compatibility shim so old bookmarks continue to work.
header('Location: news_room.php', true, 301);
exit;
