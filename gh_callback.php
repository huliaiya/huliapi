<?php
// GitHub OAuth callback entry at the site root.
// GitHub OAuth Apps allow a single callback URL; user and admin scopes share
// this entry and the originating scope is carried in the session state.
require __DIR__ . '/common/gh_connect_callback.php';
