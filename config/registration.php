<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Registration
    |--------------------------------------------------------------------------
    |
    | When false, the /register route returns a 404. This is a deploy-time
    | toggle — changing it requires a config clear or redeploy.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | The Spatie role assigned to newly registered users. Must exist in the
    | roles table. TallCMS seeds: super_admin, administrator, editor, author.
    |
    */

    'default_role' => 'author',

    /*
    |--------------------------------------------------------------------------
    | Redirect After Registration
    |--------------------------------------------------------------------------
    |
    | The URL shown on the success page's "Go to Admin Panel" button.
    |
    */

    'redirect_after' => '/admin',

];
