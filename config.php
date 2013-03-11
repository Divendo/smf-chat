<?php

/*
 * Jochem Kuijpers - 2013
 * C++Forum Chat
 * http://www.jochemkuijpers.nl/
 * http://www.cppforum.nl/chat
 */

// we need that!
require_once '../SSI.php';
require_once 'Database.class.php';

// mysql credentials
$config['mysql_host']       = '';  			// mysql host
$config['mysql_user']       = '';  			// mysql username
$config['mysql_pass']       = '';  			// mysql password
$config['mysql_db']         = '';  			// mysql database
$config['mysql_port']       = 3306;         // mysql port (default 3306)
$config['mysql_charset']    = 'utf8';       // mysql charset (e.g. 'utf8')

// avatar settings
$config['avatar_enable']    = true;         // enable serverside scaling
$config['avatar_cache']     = true;         // cache in 'avatarcache/'
$config['avatar_size']      = 32;           // 32x32 pixels
$config['avatar_image']     =               // 32x32 transparent png base64 encoded
                              'iVBORw0KGgoAA
AANSUhEUgAAACAAAAAgCAYAAABzenr0AAAABmJLR0QA/
wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB
3RJTUUH3QITAQwsxbdHmAAAAB1pVFh0Q29tbWVudAAAA
AAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAALElEQVRYw
+3OMQEAAAgDoGn/zjOGDyRg2ubT5pmAgICAgICAgICAg
ICAgMABQLQDPYU9GZMAAAAASUVORK5CYII=';