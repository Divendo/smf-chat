<?php

/*
 * Jochem Kuijpers - 2013
 * C++Forum Chat
 * http://www.jochemkuijpers.nl/
 * http://www.cppforum.nl/chat
 */

// never cache this website (because of dynamic content)
header("Cache-Control: no-cache, must-revalidate");
// expires: random date.. or is it.. ;)
header("Expires: Sat, 22 Apr 1995 13:37:00 GMT");

?>
<!DOCTYPE html>
<!--
    Jochem Kuijpers - 2013
    C++Forum Chat
    http://www.jochemkuijpers.nl/
    http://www.cppforum.nl/chat
-->
<html lang="nl">
	<head>
		<title>C++Forum.nl - Chat</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<link rel="stylesheet" type="text/css" href="style.css">
		<link rel="shortcut icon" href="favicon.ico">
		<script src="js/jquery-1.9.1.js" type="text/javascript"></script>
		<script src="js/script.js" type="text/javascript"></script>
	</head>
	<body>
		<div id="container"> <!-- contains whole page -->
            <!-- header shown at the top -->
			<div id="header">
				<div id="logo">C++Forum.nl - Chat</div> <!-- logo -->
				<div id="togglesidebar" style="display: none;" title="Geef zijbalk weer">&laquo;</div> <!-- toggle sidebar button -->
                <img id="loadingicon" src="img/loading.gif" alt="&hellip;" title="Wachten op antwoord..." style="display: none;"> <!-- visible when waiting for the server -->
                <img id="doneicon" src="img/done.png" alt="&#10004;" title="Klaar" style="display: none;"> <!-- visible when not waiting for the server -->
				<a id="chatlogout" style="display: none;">Uitloggen</a> <!-- logout button -->
			</div> <!-- end #header -->
            <!-- contains all the main elements -->
			<div id="leftcontent" style="right: 0;">
                <!-- #other is only visible when not logged in -->
				<div id="other" class="boxshadow-large">
                    <!-- noscript is only visible when JavaScript is not enabled -->
                    <noscript id="noscript" class="other">
                        <div id="noscript_title" class="other_title">
                            JavaScript is niet beschikbaar!
                        </div> <!-- end #noscript_title -->
                        <div id="noscript_message" class="other_message">
                            <p>Zorg ervoor dat JavaScript ingeschakeld is en herlaad deze pagina. Als u niet weet hoe dat moet kunt u <a href="http://www.enable-javascript.com/nl/">deze website</a> raadplegen.</p>
                            <p>Misschien ondersteunt uw huidige browser geen JavaScript, in dat geval moet u een moderner webbrowser installeren. Hieronder zijn de vijf meest populaire, gratis webbrowsers in willekeurige volgorde weergegeven. Klik op het icoontje om naar de bijbehorende download pagina te gaan en volg de instructies op om het browser te installeren.</p>
                            <div id="browsers">
                                <?php
                                // just a 'lil something to keep the haters from hatin' :)
                                $arr = array(
                                    '<div><a id="chrome" href="http://www.google.com/chrome/" target="_blank" title="Ga naar de download pagina van Google Chrome">Google Chrome</a></div>',
                                    '<div><a id="firefox" href="http://www.firefox.com/" target="_blank" title="Ga naar de download pagina van Mozilla Firefox">Mozilla Firefox</a></div>',
                                    '<div><a id="msie" href="http://windows.microsoft.com/nl-nl/internet-explorer/download-ie" target="_blank" title="Ga naar de download pagina van Internet Explorer">Internet Explorer</a></div>',
                                    '<div><a id="safari" href="http://www.apple.com/nl/safari/" target="_blank" title="Ga naar de download pagina van Apple Safari">Apple Safari</a></div>',
                                    '<div><a id="opera" href="http://www.opera.com/" target="_blank" title="Ga naar de download pagina van Opera">Opera</a></div>'
                                );
                                shuffle($arr);
                                foreach($arr as $str) { echo $str; }
                                ?>
                            </div> <!-- end #browsers -->
                        </div> <!-- end #noscript_message -->
                    </noscript>
                    <!-- #welcome is displayed when the user is asked to log in. -->
					<div id="welcome" class="other" style="display: none;">
						<div id="welcome_title" class="other_title">
                            Welkom!
                        </div>
						<div id="welcome_message" class="other_message">
                            <p>Welkom bij de C++ForumChat!</p>
                            <p id="loginhint" style="display: none">Om gebruik te maken van deze chat moet je ingelogd zijn op <a href="http://www.cppforum.nl/index.php?action=login" title="Inloggen" target="_blank">het forum</a>. Verder moet je cookies accepteren voor deze website.</p>
                            <button id="loginbutton" style="display: none;"></button>
                            <p><strong>Let op:</strong> Je wordt hier automatisch uitgelogd zodra je forumsessie verloopt. Zorg dat je sessie lang genoeg geldig is om problemen te voorkomen.</p>
                            <div id="loginuserlisttext" class="other_subtitle" style="display: none;"></div>
                            <ul id="loginuserlist" style="display: none;"></ul>
                            
                        </div> <!-- end #welcome_message -->
					</div> <!-- end #welcome -->
                    <div id="goodbye" class="other" style="display: none;">
						<div id="goodbye_title" class="other_title">Tot ziens!</div>
						<div id="goodbye_message" class="other_message">
                            <div class="other_subtitle">Gezellig dat je er was! Tot ziens!</div>
                            <p><strong>Let op:</strong> Je bent nu uitgelogd in de chat maar nog niet op het forum. Dat kun je <a href="http://www.cppforum.nl/index.php" title="Ga naar het forum">hier</a> doen door op <em>uitloggen</em> te klikken.</p>
                        </div> <!-- end #goodbye_message -->
					</div> <!-- end #goodbye -->
                    <!-- #error is visible when a fatal error has occurred (such as no connection with the server) -->
					<div id="error" class="other" style="display: none;">
						<div id="error_title" class="other_title">Er is een fout opgetreden!</div>
						<div id="error_message" class="other_message"></div>
					</div> <!-- end #error -->
				</div> <!-- end #other -->
                <!-- #chat is only visible when logged in -->
				<div id="chat" style="display: none;">
					<div id="chatbox" class="boxshadow-large">
						<div id="messages">
							<div id="intro">
								<p class="welcome">Welkom op C++ForumChat!</p>
								<p>In deze chat kun je allerlei zaken bespreken met leden van ons forum. Deze berichten worden niet vooraf gemodereerd maar blijven wel enige tijd zichtbaar voor administrators.</p>
								<p>Deel geen wachtwoorden of pincodes met andere gebruikers, en blijf vriendelijk. Als iemand zich ongepast gedraagt kun je dit melden aan een van de moderators op het forum via een persoonlijk bericht.</p>
							</div> <!-- end #intro -->
						</div> <!-- end #messages -->
					</div> <!-- end #chatbox -->
					<div id="formbox">
						<form method="POST" action="#" id="chatform">
							<div id="chatinputdiv" class="boxshadow">
								<textarea id="chatinput" name="message"></textarea>
							</div> <!-- end #chatinputdiv -->
                            <button id="chatsubmit">Verzenden</button>
						</form>
					</div> <!-- end #formbox -->
				</div> <!-- end #chat -->
			</div>
            <!-- sidebar -->
			<div id="rightcontent" style="display: none;">
                <!-- container type element -->
				<div class="box">
					<div class="boxheader">
						<div>Online leden (<span id="usersonline">0</span>)</div>
					</div> <!-- end #boxheader -->
					<div id="usersupdate" class="boxbody"></div>
				</div> <!-- end .box -->
			</div> <!-- end #rightcontent -->
		</div> <!-- end #container -->
		<div id="dialog" style="display: none;">
			<div id="dialogbox">
				<div id="dialogheader">Uitloggen</div>
				<div id="dialogbody">Weet je zeker dat je wilt uitloggen?</div>
				<div id="dialogbuttons">
					<div id="dialogyes">Ja</div>
					<div id="dialogno">Nee</div>
				</div> <!-- end #dialogbuttons -->
			</div> <!-- end #dialogbox -->
		</div> <!-- end #dialog -->
	</body>
</html>