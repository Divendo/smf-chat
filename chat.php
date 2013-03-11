<?php

/*
 * Jochem Kuijpers - 2013
 * C++Forum Chat
 * http://www.jochemkuijpers.nl/
 * http://www.cppforum.nl/chat
 */

if (trim($_SERVER['QUERY_STRING']) == '' || !isset($_POST['post'])) {
    // hello easter egg thingy !
    header('Content-type: text/plain');
    $smileys = array(':)', ':3', ':D', ':O', ':|', ':(', ':\\', '<3', '^^', ':S', ';)', ':$');
    shuffle($smileys);
    die(array_shift($smileys));
}

// json content type
header('Content-type: application/json');

// config also loads Database.class.php and ../SSI.php
require_once 'config.php';

define('CHAT_TIMEOUT', 15);

// seperate config file is not necessary because this is the only script using a database connection.. 
// $host = '', $user = '', $pass = '', $db = '', $port = 3306, $charset = ''
$database = new Database(
    $config['mysql_host'],
    $config['mysql_user'],
    $config['mysql_pass'],
    $config['mysql_db'],
    $config['mysql_port'],
    $config['mysql_charset']
);

// stores the bantime of a user when he's currently banned, set by getLoggedState()
$bantime = 0;

// used to force a userlist update
$userlist = false;

/*
 * Functions
 */

// dies and outputs failure
function dieFailure($message = '') {
    die (
        json_encode(
            array(
                'success' => false,
                'msg' => $message
            )
        )
    );
}

// dies and outputs success
function dieSuccess($array = array()) {
    die (
        json_encode(
            array_merge(
                array('success' => true), 
                $array
            )
        )
    );
}

// collects information about the users
function getUserlist() {
    global $memberContext, $database;
    
    // sql to receive userids
    $sql = 'SELECT `id` FROM `chat_users`
        WHERE `lastseen` > ' . (time() - CHAT_TIMEOUT) . '
        AND `bantime` < ' . time();
    
    // fetch all entries into a 2D assoc array (that's how the class works..)
    $result = $database->query_fetch_all($sql);
    
    // compress into a numeric array
    $userIds = array();
    foreach($result as $row) {
        $userIds[] = (int)$row['id'];
    }
    
    // if nobody is online, return an empty array and don't waste our time :)
    if (count($userIds) < 1) {
        return array('userlist' => array());
    }
    
    // load users and store found users in an array (false means
    $loadedUserIds = loadMemberData($userIds, false, 'profile');
    
    // return array
    $retarr = array();
    
    // for every found user, collect information, store in the return array
    foreach($loadedUserIds as $userId) {
        // uses $memberContext
        loadMemberContext($userId); // fills $memberContext[$userId]
        
        $name = $memberContext[$userId]['name'];
        switch($memberContext[$userId]['group']) {
            case 'Administrator': $type = 3; break;
            case 'Globale Moderator': $type = 2; break;
            case 'Lokale Moderator': $type = 1; break;
            default: $type = 0; break;
        }
        
        // add to return array
        $retarr[] = array('name' => $name, 'userid' => (int)$userId, 'type' => (int)$type, 'avatar' => ($memberContext[$userId]['avatar']['href'] != ''));
    }
    
    return array('userlist' => $retarr);
}

// finds out if the user is logged on, and if not; find out why..
function getLoggedState($session) {
    global $database, $context, $bantime;
    
    // sql to retreive userinfo - $context['user']['id'] is considdered safe as an integer.
    $sql = 'SELECT * FROM `chat_users`
        WHERE `id` = ' . (int) $context['user']['id'];
    
    $results = $database->query_fetch_all($sql);
    
    
    foreach($results as $row) {
        // check if session is matching
        if ($session != $row['session']) {
            break;
        }
        
        // no results -> user hasn't been in this chat before or logged out; -1 indicates this case
        if (!is_array($row)) { return -1; }

        // the user was timed out; -2 indicates this case
        if ($row['lastseen'] < time() - CHAT_TIMEOUT) {
            return -2;
        }

        // the user is still banned; -3 indicates this case
        if ($row['bantime'] > time()) {
            $bantime = $row['bantime'] - time();
            return -3;
        }
        
        // now it's obvious, the user is logged on to the chat
        return 1;
    }
    
    // no matching session hash; -4 indicates this case
    return -4;
}

// gets new event updates, if any.
function getEventUpdates($userId, $lastEventId) {
    global $database, $userlist;
    // first get logineventid and check if $lastEventId valid is (cannot be smaller than logineventid!)
    $sql = '
        SELECT `logineventid` FROM `chat_users`
            WHERE `id` = ' . (int) $userId . ';';
    
    $lastEventId = max($lastEventId + 1, $database->query_fetch_field($sql));
    
    // now get all events, ordered by timestamp ascending
    $sql = '
        SELECT * FROM `chat_events`
            WHERE `id` > ' . (int) ($lastEventId-1) . '
            ORDER BY `time` ASC;';
    
    $events = $database->query_fetch_all($sql);
    // return array
    $retarr = array();
    // for each event, collect necessary data
    foreach($events as $event) {
        // event data
        $retarr[] = array(
            'userid' => $event['userid'],
            'type' => $event['type'],
            'content' => $event['content']
        );
        
        // when a user joined, left or timedout, force userlist update
        if ($event['type'] >= 1 && $event['type'] <= 3) {
            $userlist = true;
        }
        
        // since lastEventId cannot be smaller than it already was.. use the same variabele :)
        $lastEventId = max($event['id'], $lastEventId);
    }
    
    return array('events' => $retarr, 'lasteventid' => $lastEventId);
}

// gets rid of the HTML and replaces \r\n by <br> if possible.
function parseMessage($message) {
    $message = str_replace(array("\r\n", "\r"), "\n", $message);
    $mesArr = explode("\n", $message);
    $retArr = array();
    
    foreach($mesArr as $str) {
        if (strlen(trim($str))) {
            $retArr[] = htmlspecialchars(trim($str));
        }
    }
    
    return implode('<br>', $retArr);
}

// send a message
function sendMessage($userId, $message) {
    $message = trim($message);
    
    if ($message == '') {
        return array();
    }
    
    // limit message length to 2048 characters (2kb)
    if (strlen($message) > 2048) {
        $message = substr($message, 0, 2048) . "\n" . '...';
    }
    
    // we might someday add a bad-word filter, or some chatcommands... who knows.. ;)
    createEvent($userId, parseMessage($message));
    return array();
}

// create any event (type; 0: message, 1: user joined, 2: user left, 3: user timedout)
function createEvent($userId, $content = '', $type = 0) {
    global $database;
    $sql = '
        INSERT INTO `chat_events`
            (`id`, `type`, `time`, `userid`, `content`)
            VALUES (NULL, ' . (int) $type . ', ' . time() . ', ' . (int) $userId . ', \'' . $database->escape($content) . '\');';
    $database->query($sql);
    return $database->insertId();
}

// login
function logIn($userId) {
    global $database;
    // login event (type 1)
    $eventid = createEvent($userId, '', 1);
    
    // semi-random session string
    $session = sha1('hello' . date('r') . md5('this is md5' . $userId . 'blablabla') . microtime(false) . md5('this is also md5' . $eventid . 'blabla!') . 'world');
    
    $sql = '
        INSERT INTO `chat_users`
            (`id`, `lastseen`, `logineventid`, `bantime`, `session`)
            VALUES (' . (int)$userId . ', ' . time() . ', ' . $eventid . ', 0, \'' . $session . '\')
            ON DUPLICATE KEY UPDATE 
                `lastseen` = VALUES(`lastseen`),
                `logineventid` = VALUES(`logineventid`),
                `session` = VALUES(`session`);';
    
    $database->query($sql);
    
    return array(
        'lasteventid' => $eventid,
        'userid' => $userId,
        'session' => $session
    );
}

// logout
function logOut($userId) {
    global $database;
    
    // logout event (type 2)
    $eventid = createEvent($userId, '', 2);
    
    $sql = '
        DELETE FROM `chat_users`
            WHERE `id` = ' . (int)$userId . '
            AND `session` = \'' . $database->escape($_POST['session']) . '\'
            AND `bantime` < ' . time(). ';';
    
    $database->query($sql);
    
    return array();
}

// timeout
function TimeOut($userId) {
    global $database;
    
    // logout event (type 2)
    $eventid = createEvent($userId, '', 2);
    
    $sql = '
        DELETE FROM `chat_users`
            WHERE `id` = ' . (int)$userId . '
            AND `bantime` < ' . time(). ';';
    
    $database->query($sql);
    
    return array();
}

// update lastseen value and check if other users timed out
function preventTimeout($userId) {
    global $database;
    
    // prevent own timeout
    $sql = '
        UPDATE `chat_users`
            SET `lastseen` = ' . time() . '
            WHERE `id` = ' . (int) $userId . '
            LIMIT 1;';
    
    $database->query($sql);
    
    // check if any users timed out
    // lastseen < time() - CHAT_TIMEOUT*2 means the user was already recognised as 'timed out'
    // also don't timeout banned users
    $sql = '
        SELECT `id` FROM `chat_users`
            WHERE `lastseen` < ' . (time() - CHAT_TIMEOUT) . '
            AND `lastseen` > ' . (time() - CHAT_TIMEOUT*2) . '
            AND `bantime` < ' . time() . ';';
    
    // create timeout event and set lastseen to 0
    $result = $database->query_fetch_all($sql);
    foreach($result as $row) {
        // timeout event (type 3)
        createEvent($row['id'], '', 3);
        
        // set lastseen to 0 for timedout users to ensure everyone will see them as timedout and they aren't timedout twice
        $sql = '
            UPDATE `chat_users`
                SET `lastseen` = 0
                WHERE `id` = ' . (int)$row['id'] . '
                LIMIT 1;';
        $database->query($sql);
    }
    
    return true;
}

// relative text representation of time
function relativeTime($seconds) {
    // no negative seconds!
    $seconds = max(0, $seconds);
    
    if ($seconds <= 60) {
        return $seconds . (($seconds != 1)?' seconden':' seconde');
    }
    if ($seconds <= 3600) {
        return floor($seconds/60) . ((floor($seconds/60) > 1)?' minuten':' minuut');
    }
    if ($seconds <= 24*3600) {
        return floor($seconds/3600) . ' uur'; // 1 uur, 2 uur, 3 uur, etc.
    }
    if ($seconds <= 7*24*3600) {
        return floor($seconds/(24*3600)) . ((floor($seconds/24*3600) > 1)?' dagen':' dag');
    }
    if ($seconds <= 30.437*24*3600) {
        return floor($seconds/(7*24*3600)) . ((floor($seconds/(7*24*3600)) > 1)?' weken':' week');
    }
    if ($seconds <= 365.24*24*3600) {
        return floor($seconds/(30.437*24*3600)) . ((floor($seconds/(30.437*24*3600)) > 1)?' maanden':' maand');
    }
    return floor($seconds/(365.24*24*3600)) . ' jaar'; // 1 jaar, 2 jaar, 3 jaar, etc..
}

/*
 * Script
 */

// validate query string
// first split it, since jQuery adds something to prevent caching
$queryArray = explode('&', $_SERVER['QUERY_STRING']);
// then take the first part
$requestType = array_shift($queryArray);
// then validate or die
switch($requestType) {
    case 'username':    // request username
    case 'userlist':    // request userlist
    case 'login':       // request login
    case 'logout':      // request logout
    case 'update':      // request update (events and userlist)
    case 'send':        // send a message
        break;
    default:
        dieFailure('De server begreep het verzoek <span style="font-family: monospace;">' . htmlentities($_SERVER['QUERY_STRING']) . '</span> niet.');
        break;
}

// check if the user is logged on to the forum except for requests a guest is allowed to make (username and userlist) 
if (!$context['user']['is_logged'] && $requestType != 'username' && $requestType != 'userlist') {
    dieFailure('Je forumsessie is verlopen of je bent niet ingelogd. Klik <a href="http://www.cppforum.nl/index.php?action=login" target="_blank">hier</a> om in te loggen met je forumaccount.');
}

// get logged state (-3: ban, -2: timed out, -1: no record, 1: logged on)
$loggedState = getLoggedState(((isset($_POST['session']))?$_POST['session']:''));
if ($loggedState < 0) {
    if ($loggedState == -3) {
        dieFailure('Je bent verbannen van deze chat. Over ' . relativeTime($bantime) . ' kun je deze chat weer gebruiken. Neem contact op met een administrator als je het hier niet mee eens bent.');
    }
    
    if ($requestType != 'username' && $requestType != 'userlist' && $requestType != 'login') {
        if ($loggedState == -2) {
            dieFailure('Je bent uitgelogd omdat de server te lang niets van je heeft vernomen (timeout).');
        }
        dieFailure('Je bent niet ingelogd!');
    }
} else {
    preventTimeout($context['user']['id']);
}

// --- from now on: the user is logged on or is allowed to do the request anyway, no worries about that!

switch($requestType) {
    case 'username':
        // for guests, this will be an empty string
        $array = array('name' => $context['user']['username']);
        dieSuccess($array);
        break;
    case 'userlist':
        // generate userlist
        $array = getUserList();
        dieSuccess($array);
        break;
    case 'login':
        $array = logIn($context['user']['id']);
        dieSuccess($array);
        break;
    case 'logout':
        $array = logOut($context['user']['id']);
        dieSuccess($array);
        break;
    case 'update':
        // find updates
        $array = getEventUpdates($context['user']['id'], ((isset($_POST['lasteventid']))?$_POST['lasteventid']:0));
        // update userlist when a leave or join event has occurred
        if ($userlist) {
            $array = array_merge($array, getUserList());
        }
        dieSuccess($array);
        break;
    case 'send':
        // send the message
        // find updates
        $array = array_merge(
            sendMessage($context['user']['id'], ((isset($_POST['message']))?$_POST['message']:'')),
            getEventUpdates($context['user']['id'], ((isset($_POST['lasteventid']))?$_POST['lasteventid']:0))
        );
        // update userlist when a leave or join event has occurred
        if ($userlist) {
            $array = array_merge($array, getUserList());
        }
        dieSuccess($array);
        break;
    default:
        // somehow we didn't know about this request type previously allowed.. just die and fail
        dieFailure();
        break;
}

// This part of the code is never executed because of dieFailure or dieSuccess