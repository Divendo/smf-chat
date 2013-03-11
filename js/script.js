/*
 * Jochem Kuijpers - 2013
 * C++ForumChat
 * http://www.jochemkuijpers.nl/
 * http://www.cppforum.nl/chat/
 */

// --- Variables

// chatLastEventId for requesting new events
var chatLastEventId = 0;
// chatLastUsername for adding names above messages
var chatLastUsername = '';
// chatLastTimeSting for adding time to messages
var chatLastTimeString = '';
// chatScrollHeight stores the maximum scroll height of the chat
var chatScrollHeight = 0;
// chatResponse contains the response of the server
var chatResponse = {};
// chatNumberOfEvents is used to calculate the timer interval
var chatNumberOfEvents = [0];
// chatSession is the hash used to determine who you are and if you're logged on right now..
var chatSession = '';

// - users
// chatUsers stores all the currently online users
var chatUsers = {};
// chatUserTypes stores the names of the usertypes
var chatUserTypes = ['Gebruiker', 'Moderator', 'Globale Moderator', 'Administrator'];
// chatUserName contains the username of the local user
var chatUserName = '';
// chatUserId contains the userid of the local user
var chatUserId = -1;

// - connections
// chatNumRequests keeps track of the currently active ajax requests
var chatNumRequests = 0;
// chatTimeoutTime is the amount of seconds the script will wait before triggering a timeout
// IMPORTANT!!! EQUAL OR GREATER THAN THE SERVER TIMEOUT!!
var chatTimeoutTime = 15;

// - booleans
// chatIsLogged, whether or not the user is logged on
var chatIsLogged = false;
// chatErrorState tells the script when a fatal error has occured
var chatErrorState = false;
// chatUpdating prevents multiple update requests
var chatUpdating = false;

// - timers
// chatTimerUpdate is the update request timer
var chatTimerUpdate = 0;

// - smileys
// chatSmileys contains the smileys and filenames
var chatSmileys = {
    ':)': 'smile.png',
    ';)': 'wink.png',
    ':D': 'veryhappy.png',
    ';D': 'cheesy.png',
    ':P': 'tongue.png',
    '^^': 'enjoying.png',
    'B)': 'cool.png',
    '8)': 'eyes.png',
    'xD': 'xD.png',
    ':*': 'kiss.png',
    ':S': 'confused.png',
    ':O': 'shocked.png',
    ':|': 'mellow.png',
    ':(': 'unhappy.png',
    ':@': 'angry.png',
    ':/': 'undecided.png',
    ':$': 'embarrassed.png',
    ':\'(': 'cry.png',
    ':x': 'mouthshut.png',
    '!!!': 'exclamationmark.png',
    '???': 'questionmark.png',
    ':tux:': 'Tux.png',
    ':win:': 'Windows.png',
    ':ubu:': 'Ubuntu.png',
    ':mac:': 'Mac.png'
};
// chatSmileyBase contains the base URL
var chatSmileyBase = 'http://www.cppforum.nl/Smileys/new/';
// chatSmileyPatterns will contain the generated smiley patterns
var chatSmileyPatterns = [];
// chatMetaChars is used to generate the patterns
var chatMetaChars = /[[\]{}()*+?.\\|^$\-,&#\s]/g;
// prepare the replace patterns for the smileys
for (var i in chatSmileys) {
    if (chatSmileys.hasOwnProperty(i)) {
        if (i == ':/') {
            chatSmileyPatterns.push('('+i.replace(chatMetaChars, "\\$&")+')(?!\/)');
        } else {
            chatSmileyPatterns.push('('+i.replace(chatMetaChars, "\\$&")+')');
        }
    }
}

// --- AJAX Functions (actually it's not AJAX because it uses JSON instead of XML, but who cares..)

// success handler
function chatAjaxSuccessHandler(response) {
    chatUpdateLoadingIcon(-1);
    
    // process response
    response = Object(response);
    chatResponse = response; // when this line triggers an error, it means the JSON was invalid.
    chatResponse.success = chatResponse.success || false;
    if (!chatResponse.success) {
        if (chatResponse.msg != undefined && chatResponse.msg != '') {
            if (!chatErrorState){
                chatFatalError(chatResponse.msg);
            }
        }
        return false;
    }
    return true;
}

// error handler
function chatAjaxErrorHandler(jqXHR, textStatus, errorThrown) {
    chatUpdateLoadingIcon(-1);
    
    // self-explanatory
    if (textStatus == 'timeout') {
        chatTimeout();
    }
    
    // log error
    console.error('jQuery.ajax() failed. textStatus: \'' + textStatus + '\', errorThrown: \'' + errorThrown + '\'');
}

// before send
function chatAjaxBeforeSend(jqXHR, settings) {
    chatUpdateLoadingIcon(1);
    
    // if somehow requests are accumulating, trigger a fatal error
    if (chatNumRequests > 10) {
        chatFatalError('Meer dan 10 gelijktijdige verbindingen met de server.');
        return false;
    }
    // return true so the request is actually send
    return true;
}

// enables the login button when you're able to log in, otherwise it won't do anything
function chatEnableLoginButton() {
    $.ajax({
        url: 'chat.php?username',
        data: {
            'post': true
        },
        success: function(response) {
            if (chatAjaxSuccessHandler(response)) {
                chatUserName = chatResponse.name || chatUserName;
                if (chatUserName != '') {
                    // show loginbutton, add event handler for login
                    $("#loginbutton")
                    .fadeIn(500)
                    .text('Inloggen als ' + chatUserName)
                    .click(chatRequestLogin);
                    // end #loginbutton
                } else {
                    // not logged in? show #loginhint
                    $("#loginhint").slideDown(500);
                }
            } else {
                // something failed? show #loginhint
                $("#loginhint").slideDown(500);
            }
            
        }
    });
}

// loads userlist which shows up on the welcome screen, to keep it simple: this function doesn't store the online users in `chatUsers`
function chatLoadWelcomeUserlist() {
    $.ajax({
        url: 'chat.php?userlist',
        data: {
            'post': true
        },
        success: function(response) {
            // we no longer need you
            $('#loginloading').fadeOut(500);
            if (chatAjaxSuccessHandler(response)) {
                var userlist = chatResponse.userlist || [];
                var element, n = 0;
                
                // for every valid userlist item, create a <li> element
                for(i in userlist) {
                    if (userlist[i].name != undefined && userlist[i].userid != undefined && userlist[i].type != undefined) {
                        n++;
                        element = $('<li>');
                        element.text(userlist[i].name);
                        switch(userlist[i].type) {
                            case 1:
                                element.addClass('mod');
                                break;
                            case 2:
                                element.addClass('globmod');
                                break;
                            case 3:
                                element.addClass('admin');
                                break;
                        }
                        // append them to the userlist
                        $('#loginuserlist').append(element);
                    }
                }
                
                if (n == 0) {
                    $('#loginuserlisttext').text('Er is nog niemand online.').fadeIn(500);
                } else if (n == 1) {
                    $('#loginuserlisttext').text('Er is momenteel één persoon online:').fadeIn(500);
                    $('#loginuserlist').slideDown(500);
                } else {
                    $('#loginuserlisttext').text('De volgende leden zijn momenteel online:').fadeIn(500);
                    $('#loginuserlist').slideDown(500);
                }
            }
        }
    });
}

// requests login
function chatRequestLogin() {
    $.ajax({
        url: 'chat.php?login',
        data: {
            'post': true
        },
        success: function(response) {
            chatIsLogged = false;
            if (chatAjaxSuccessHandler(response)) {
                if (chatResponse.lasteventid != undefined && chatResponse.userid != undefined) {
                    chatLastMessageId = chatResponse.lasteventid;
                    chatUserId = chatResponse.userid;
                    chatSession = chatResponse.session;
                    chatIsLogged = true;
                } else {
                    chatFatalError('De server gaf een onverwacht resultaat tijdens het inloggen.');
                }
            } else {
                chatFatalError('Het inloggen is mislukt.');
            }
            
            
            if (chatIsLogged) {
                $('#welcome').fadeOut(500, function() {
                    $("#togglesidebar")
                    .fadeIn(500)
                    .click(function () {
                        chatToggleSidebar();
                    });
                    // end #togglesidebar
                    $('#other').hide();
                    $('#chat').fadeIn(500);
                    $('#chatlogout').fadeIn(500).click(chatToggleDialog);
                    $("#dialogno").click(function () {
                        chatToggleDialog(false);
                    });

                    $("#dialogyes").click(function () {
                        chatRequestLogout(true);
                        chatToggleDialog(false);
                    });
                    if ($(window).width() > 800) {
                        chatToggleSidebar();
                    }
                    chatRequestUpdate();
                });
            }
        }
    });
}

// requests logout (will fail when timed out serverside)
function chatRequestLogout(async) {
    if (!chatIsLogged) {
        return;
    }
    chatIsLogged = false;
    
    // stop updates
    clearTimeout(chatTimerUpdate);
    
    // we don't really care about the response, we're done..
    $.ajax({
        url: 'chat.php?logout',
        data: {
            'session': chatSession,
            'post': true
        },
        async: async, 
        success: chatAjaxSuccessHandler
    });
    
    // do not show anything when a fatal error is triggered.
    if (chatErrorState) {
        return;
    }
    
    $("#togglesidebar").fadeOut(500);
    $("#chatlogout").fadeOut(500);
    if (chatSidebarVisible()) {
        chatToggleSidebar();
    }
    $('#chat').fadeOut(500, function () {
        if (!chatErrorState) {
            $('#other').show();
            $('#welcome').hide();
            $('#goodbye').fadeIn(500);
        }
    });
}

// process update response
function chatRequestUpdateSuccess() {
    // function to calculate the time between updates:
    // y = min(5000, 10000 / (x + 2) + 10 * x)
    // where y = milliseconds to wait and x = number of events in the past 10 updates
    var x = chatNumberOfEvents.reduce( function(a, b) {return a + b; } ), // calculates the sum of the array
        timer = Math.min(5000, 10000 / (x + 2) + 10 * x); // ms
    // set update timer
    chatTimerUpdate = setTimeout(chatRequestUpdate, timer);

    // handle userlist update
    if (chatResponse.userlist != undefined) {
        chatHandleUserlistUpdate(chatResponse.userlist);
    }

    // update latest messageid and handle new messages
    if (chatResponse.events != undefined) {
        if (chatResponse.events.length > 0) {
            chatHandleEventUpdate(chatResponse.events);
            chatLastEventId = chatResponse.lasteventid || chatLastEventId;
        }
    }
}

// requests new messages since lastmessageid and the userlist if anyone joined or left the chat
function chatRequestUpdate() {
    // to be safe..
    clearTimeout(chatTimerUpdate);
    
    // don't update while already updating
    if (chatUpdating) {
        return;
    }
    chatUpdating = true;
    
    $.ajax({
        type: 'POST',
        url: 'chat.php?update',
        data: {
            'lasteventid': chatLastEventId,
            'session': chatSession,
            'post': true
        },
        success: function(response) {
            // somehow it's possible that an update is triggered
            if (!chatIsLogged) { 
                return;
            }
            
            if (chatAjaxSuccessHandler(response)) {
                chatUpdating = false;
                // seperate function, because sendMessage() also receives event updates
                chatRequestUpdateSuccess();
            }
        }
    });
}

// sends a new message
function chatSendMessage(message) {
    // blink when sent
    $('#chatinputdiv').css('border', '1px solid red').delay(100).css('border','');
    $('#chatinputdiv').val('');
    
    // set the update timer to the timeout limit
    if (!chatUpdating) {
        clearTimeout(chatTimerUpdate);
        chatTimerUpdate = setTimeout(chatRequestUpdate, chatTimeoutTime*1000);  
    }
    
    $.ajax({
        type: 'POST',
        url: 'chat.php?send',
        data: {
            'message': message,
            'lasteventid': chatLastEventId,
            'session': chatSession,
            'post': true
        },
        success: function(response) {
            $('#chatinput')
            if (chatAjaxSuccessHandler(response)) {
                chatRequestUpdateSuccess();
            }
        }
    });
}

// --- Update handling functions

// CSS suffix
function chatGetSuffix(type) {
    switch (type) {
        case 1:
            return '_mod';
            break;
        case 2:
            return '_globmod';
            break;
        case 3:
            return '_admin';
            break;
        case 0:  // ``
        default:
            return '';
            break;
    }
}

// handle JSON userlist data
function chatHandleUserlistUpdate(userlist) {
    var n = 0, str = '', suffix, img;
    
    for(i in userlist) {
        n += 1;
        
        // CSS suffix
        suffix = chatGetSuffix(userlist[i].type);
        
        // avatar, if set create image element, else: leave empty
        if (userlist[i].avatar == true) {
            img = '<img src="avatar.php?u=' + userlist[i].userid + '" alt="">';
        } else {
            img = '';
        }
        
        str += '<div class="user' + suffix + '" title="' + chatUserTypes[userlist[i].type] + '"><div class="user_name">' + userlist[i].name + ' <a class="user_profile" href="http://www.cppforum.nl/index.php?action=profile;u=' + userlist[i].userid + '" target="_blank"><img src="http://www.cppforum.nl/Themes/whitebox_20g/images/icons/profile_sm.gif" alt="Profiel"></a></div><div class="user_avatar">' + img + '</div></div>';
        chatUsers[userlist[i].userid] = userlist[i];
    }
    $("#usersupdate").html(str);
    $("#usersonline").html(n);
}

// handle JSON event data
function chatHandleEventUpdate(events) {
    var n = 0, str = '', date, timestr, suffix, username;
    
    date = new Date();
	timestr = date.getHours() + ":" + ((date.getMinutes() < 10)?'0':'') + date.getMinutes();
    
    // check if it's a new time, otherwise leave empty
    if (timestr == chatLastTimeString) {
        timestr = '';
    } else {
        chatLastTimeString = timestr;
        timestr = ' om ' + timestr;
    }
    
    for(i in events) {
        n += 1;
        
        username = chatUsers[events[i].userid].name;
        // only hide username if it's a normal event (message)
        if (events[i].type == 0) {
            if (username == chatLastUsername) {
                username = '';
            } else {
                chatLastUsername = username;
            }
        }
        
        // CSS suffix
        suffix = chatGetSuffix(chatUsers[events[i].userid].type);
        
        switch(events[i].type) {
            case '0': // normal message
                str += '<div class="msg_normal">';
                if (username) {
                    str += '<span class="msg_username' + suffix + '">' + username + '<span class="msg_small"> zegt' + timestr + ':</span></span>';
                }
                str += '<div class="msg_content">' + chatParseMessage(events[i].content) + '</div></div>';
                break;
            case '1': // someone joined
                str += '<div class="msg_info"><span class="icon_plus' + suffix + '"></span><span class="msg_username' + suffix + '">' + username + '</span> is' + timestr + ' ingelogd.</span></div>';
                break;
            case '2': // someone left
                str += '<div class="msg_info"><span class="icon_min' + suffix + '"></span><span class="msg_username' + suffix + '">' + username + '</span> is' + timestr + ' uitgelogd.</span></div>';
                break;
            case '3': // someone timed out
                str += '<div class="msg_info"><span class="icon_min' + suffix + '"></span><span class="msg_username' + suffix + '">' + username + '</span> is' + timestr + ' weggevallen.</span></div>';
                break;
            case '4': // alert message
                str += '<div class="msg_alert"><span class="icon_alert' + suffix + '"></span><span class="msg_username' + suffix + '">' + username + '<span class="msg_small"> zegt' + timestr + ':</span></span>';
                str += '<div class="msg_content">' + chatParseMessage(events[i].content) + '</div></div>';
                break;
            default:
                console.warn('chatHandleEventUpdate(): Unknown event type');
                break;
        }
    }
    $("#messages").append(str);
    chatScroll();
    return n;
}

function chatParseMessage(message){
	var output = message;
    var replacePattern = /(?:^|[^"'])((ftp|http|https|file):\/\/)?(([a-zA-Z0-9_\-]+\.)+[a-zA-Z]{2,6}([\S])*(\b|$))/gim;
    output = output.replace(replacePattern, function (match) {
		if (match.indexOf('://') > 0) {
			return '<a href="' + match + '" target="_blank">' + match + '</a>';
		} else {
			return '<a href="http://' + match + '" target="_blank">' + match + '</a>';
		}
	});
	
	output = output.replace(new RegExp(chatSmileyPatterns.join('|'),'g'), function (match) {
		return typeof (chatSmileys[match] != 'undefined')?'<img class="smiley" src="' + chatSmileyBase + chatSmileys[match] + '" alt="' + match + '">':match;
	});
	
	return output;
}

// --- Other functions

// fatal error
function chatFatalError(message) {
    // sorry, someone else got here first!
    if (chatErrorState) {
        return;
    }
    
    // set error message
    $('#error_message').html('<div class="other_subtitle">' + message + '</div><p>Probeer <a href="index.php">opnieuw in te loggen</a> en meld dit aan een administrator a.u.b.</p>');
    
    chatErrorState = true;
    
    // hide things
    $('#loadingicon').fadeOut(250);
    $('#doneicon').fadeOut(250);
    $('#welcome').hide();
    $('#goodbye').hide();
    
    // chatIsLogged means it's currently showing the chat part of the page (and we need to request a logout)
    if (chatIsLogged) {
        chatRequestLogout(true);
        $('#togglesidebar').fadeOut(250);
        $('#chatlogout').fadeOut(250);
        $('#chat').fadeOut(250, function() {
            $('#other').show();
            $('#error').fadeIn(250);
        });
        // end #chat
    } else {
        // just hide everything else and show the error..
        $('#chat').hide();
        $('#other').show();
        $('#error').fadeIn(250);
    }
    
    // hide sidebar if visible
    if (chatSidebarVisible()) {
        chatToggleSidebar();
    }
}

// checks if the sidebar is visible
function chatSidebarVisible() {
    if ($("#rightcontent").css('display') == 'block') {
        return true;
    } else {
        return false;
    }
}

// toggles the sidebar
function chatToggleSidebar() {
    if ($("#rightcontent").css('display') == 'block') {
        $("#rightcontent").css('display', 'none');
        $("#leftcontent").css('right', '0');
        $("#togglesidebar").removeClass('toggle').html('&laquo;').attr('title', 'Geef zijbalk weer');
    } else {
        $("#rightcontent").css('display', 'block');
        $("#leftcontent").css('right', '');
        $("#togglesidebar").addClass('toggle').html('&raquo;').attr('title', 'Verberg zijbalk');;
    }
}

// toggles the logout dialogbox
function chatToggleDialog(state) {
    $("#dialog").css('display', (state)?'block':'none');
}

// update the loading / done icon
function chatUpdateLoadingIcon(delta) {
    chatNumRequests += delta;
    
    if (chatErrorState) {
        return;
    }
    
    if (chatNumRequests > 0) {
        $('#loadingicon').show();
        $('#doneicon').hide();
    } else {
        $('#loadingicon').hide();
        $('#doneicon').show();
    }
}

// triggers when a connection times out
function chatTimeout() {
    chatFatalError('De verbinding met de server is verbroken (de server liet te lang op zich wachten).');
}

// scrolls the chatbox
function chatScroll(force) {
	var el = document.getElementById("messages");
	if (el.scrollTop > chatScrollHeight-16 || force) {
		el.scrollTop = el.scrollHeight-el.parentNode.clientHeight;
		chatScrollHeight = el.scrollHeight-el.parentNode.clientHeight;
	}
}

// --- Script

$(document).ready(function(){
    // display the welcome block
    $('#welcome').css('display', 'block');
    
    // make chatinput work with shift and enter
    $('#chatinput').keypress(function(e) {
        var key = e.which || e.keyCode;
        
        if(key == 13 && !e.shiftKey) {
            chatSendMessage($(this).val());
            $(this).val('');
            e.preventDefault();
        }
    }); // end #chatinput
    
    // make the send button send the input as message
    $('#chatsubmit').click(function(e) {
        chatSendMessage($('#chatinput').val());
        $('#chatinput').val('');
        e.preventDefault();
    })
    
    // make the form submit send the input as message
    $('#chatform').submit(function(e) {
        chatSendMessage($('#chatinput').val());
        $('#chatinput').val('');
        e.preventDefault();
    })
    
    // force to scroll down on window resize, ask whether or not you want to leave the page and logout when you do so
    $(window)
        .resize(function() { chatScroll(true); })
        .on('beforeunload', function(){ if (chatIsLogged) { return 'Weet je zeker dat je de chat wilt verlaten?'; } })
        .on('unload', function(){ if (chatIsLogged) { chatRequestLogout(false); } }); // unload cannot be asynchronous...
    // end window
    
    // some defaults
    $.ajaxSetup({
        type: 'POST',
        dataType: 'json',
        cache: false,
        async: true,
        timeout: chatTimeoutTime * 1000,
        beforeSend: chatAjaxBeforeSend,
        error: chatAjaxErrorHandler
    });
    
    // check for username, enable button if the server gives us an username
    chatEnableLoginButton();
    // load currently online users
    chatLoadWelcomeUserlist();
    
    
});