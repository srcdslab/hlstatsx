<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

HLstatsX Community Edition is a continuation of 
ELstatsNEO - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
http://ovrsized.neo-soft.org/

ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
http://www.hlstatsx.com/
Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

HLstatsX is an enhanced version of HLstats made by Simon Garner
HLstats - Real-time player and clan rankings and statistics for Half-Life
http://sourceforge.net/projects/hlstats/
Copyright (C) 2001  Simon Garner
            
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

For support and installation notes visit http://www.hlxcommunity.com
*/
class Auth
{
	var $ok = false;
	var $error = false;

	var $username, $password, $savepass;
	var $sessionStart, $session;

	var $userdata = array();

	function Auth()
	{
		//@session_start();

		if (valid_request($_POST['authusername'], 0))
		{
			$this->username = valid_request($_POST['authusername'], 0);
			$this->password = valid_request($_POST['authpassword'], 0);
			$this->savepass = valid_request($_POST['authsavepass'], 0);
			$this->sessionStart = 0;

			# clear POST vars so as not to confuse the receiving page
			$_POST = array();

			$this->session = false;

			if($this->checkPass()==true)
			{
				// if we have success, save it in this users SESSION
				$_SESSION['username']=$this->username;
				$_SESSION['password']=$this->password;
				$_SESSION['authsessionStart']=time();
				$_SESSION['acclevel'] = $this->userdata['acclevel'];
			}
		}
		elseif (isset($_SESSION['loggedin']))
		{
			$this->username = $_SESSION['username'];
			$this->password = $_SESSION['password'];
			$this->savepass = 0;
			$this->sessionStart = $_SESSION['authsessionStart'];
			$this->ok = true;
			$this->error = false;
			$this->session = true;

			if(!$this->checkPass())
			{
				unset($_SESSION['loggedin']);
			}
		}
		else
		{
			$this->ok = false;
			$this->error = false;

			$this->session = false;

			$this->printAuth();
		}
	}

	function checkPass()
	{
		global $db;

		$db->query("
				SELECT
					*
				FROM
					hlstats_Users
				WHERE
					username='$this->username'
				LIMIT 1
			");

		if ($db->num_rows() == 1)
		{
			// The username is OK

			$this->userdata = $db->fetch_array();
			$db->free_result();

			if (md5($this->password) == $this->userdata["password"])
			{
				// The username and the password are OK

				$this->ok = true;
				$this->error = false;
				$_SESSION['loggedin']=1;
				if ($this->sessionStart > (time() - 3600))
				{
					// Valid session, update session time & display the page
					$this->doCookies();
					return true;
				}
				elseif ($this->sessionStart)
				{
					// A session exists but has expired
					if ($this->savepass)
					{
						// They selected 'Save my password' so we just
						// generate a new session and show the page.
						$this->doCookies();
						return true;
					}
					else
					{
						$this->ok = false;
						$this->error = 'Your session has expired. Please try again.';
						$this->password = '';

						$this->printAuth();
						return false;
					}
				}
				elseif (!$this->session)
				{
					// No session and no cookies, but the user/pass was
					// POSTed, so we generate cookies.
					$this->doCookies();
					return true;
				}
				else
				{
					// No session, user/pass from a cookie, so we force auth
					$this->printAuth();
					return false;
				}
			}
			else
			{
				// The username is OK but the password is wrong

				$this->ok = false;
				if ($this->session)
				{
					// Cookie without 'Save my password' - not an error
					$this->error = false;
				}
				else
				{
					$this->error = 'The password you supplied is incorrect.';
				}
				$this->password = '';
				$this->printAuth();
			}
		}
		else
		{
			// The username is wrong
			$this->ok = false;
			$this->error = 'The username you supplied is not valid.';
			$this->printAuth();
		}
	}

	function doCookies()
	{
		return;
		setcookie('authusername', $this->username, time() + 31536000, '', '', 0);

		if ($this->savepass)
		{
			setcookie('authpassword', $this->password, time() + 31536000, '', '', 0);
		}
		else
		{
			setcookie('authpassword', $this->password, 0, '', '', 0);
		}
		setcookie('authsavepass', $this->savepass, time() + 31536000, '', '', 0);
		setcookie('authsessionStart', time(), 0, '', '', 0);
	}

	function printAuth()
	{
		global $g_options;

		include (PAGE_PATH . '/adminauth.php');
	}
}

$auth = new Auth;
if ($auth->ok === false || $auth->userdata['acclevel'] < 80)
{
	return;
}

if ( !defined('IN_HLSTATS') )
{
	die('Do not access this file directly.');
}

// Player Chat History
	$player = valid_request(intval($_GET['player']), 1)
		or error('No player ID specified.');
	$db->query
	("
		SELECT
			unhex(replace(hex(hlstats_Players.lastName), 'E280AE', '')) as lastName,
			hlstats_Players.game
		FROM
			hlstats_Players
		WHERE
			hlstats_Players.playerId = $player
	");
	if ($db->num_rows() != 1)
	{
		error("No such player '$player'.");
	}
	$playerdata = $db->fetch_array();
	$pl_name = $playerdata['lastName'];
	if (strlen($pl_name) > 10)
	{
		$pl_shortname = substr($pl_name, 0, 8) . '...';
	}
	else
	{
		$pl_shortname = $pl_name;
	}
	$pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
	$pl_shortname = htmlspecialchars($pl_shortname, ENT_COMPAT);
	$game = $playerdata['game'];
	$db->query
	("
		SELECT
			hlstats_Games.name
		FROM
			hlstats_Games
		WHERE
			hlstats_Games.code = '$game'
	");
	if ($db->num_rows() != 1)
	{
		$gamename = ucfirst($game);
	}
	else
	{
		list($gamename) = $db->fetch_row();
	}
	pageHeader
	(
		array ($gamename, 'Chat History', $pl_name),
		array
		(
			$gamename=>$g_options['scripturl'] . "?game=$game",
			'Player Rankings'=>$g_options['scripturl'] . "?mode=players&game=$game",
			'Player Details'=>$g_options['scripturl'] . "?mode=playerinfo&player=$player",
			'Chat History'=>''
		),
		$playername
	);
	flush();
	$table = new Table
	(
		array
		(
			new TableColumn
			(
				'eventTime',
				'Date',
				'width=16'
			),

			new TableColumn
			(
				'message',
				'Message',
				'width=44&sort=no&append=.&embedlink=yes'
			),
			new TableColumn
			(
				'serverName',
				'Server',
				'width=24'
			),
			new TableColumn
			(
				'map',
				'Map',
				'width=16'
			)
		),
		'eventTime',
		'eventTime',
		'serverName',
		false,
		50,
		'page',
		'sort',
		'sortorder'
	);
	$surl = $g_options['scripturl'];
	
	$whereclause="hlstats_Events_Chat.playerId = $player ";
	$filter=isset($_REQUEST['filter'])?$_REQUEST['filter']:"";
	if(!empty($filter))
	{
				$whereclause.="AND MATCH (hlstats_Events_Chat.message) AGAINST ('" . $db->escape($filter) . "' in BOOLEAN MODE)";
	}
	
	$result = $db->query
	("
		SELECT
			hlstats_Events_Chat.eventTime,
			IF(hlstats_Events_Chat.message_mode=2, CONCAT('(Team) ', hlstats_Events_Chat.message), IF(hlstats_Events_Chat.message_mode=3, CONCAT('(Squad) ', hlstats_Events_Chat.message), hlstats_Events_Chat.message)) AS message,
			hlstats_Servers.name AS serverName,
			hlstats_Events_Chat.map
		FROM
			hlstats_Events_Chat
		LEFT JOIN 
			hlstats_Servers
		ON
			hlstats_Events_Chat.serverId = hlstats_Servers.serverId
		WHERE
			$whereclause	
		ORDER BY
			$table->sort $table->sortorder,
			$table->sort2 $table->sortorder
		LIMIT
			$table->startitem,
			$table->numperpage
	");
	
	$resultCount = $db->query
	("
		SELECT
			COUNT(*)
		FROM
			hlstats_Events_Chat
		LEFT JOIN 
			hlstats_Servers
		ON
			hlstats_Events_Chat.serverId = hlstats_Servers.serverId
		WHERE
			$whereclause
	");
			
	list($numitems) = $db->fetch_row($resultCount);
	
?>
<div class="block">
<?php
	printSectionTitle('Player Chat History (Last '.$g_options['DeleteDays'].' Days)');
?>
	<div class="subblock">
		<div style="float:left;">
			<span>
			<form method="get" action="<?php echo $g_options['scripturl']; ?>" style="margin:0px;padding:0px;">
				<input type="hidden" name="mode" value="chathistory" />
				<input type="hidden" name="player" value="<?php echo $player; ?>" />
				<strong>&#8226;</strong>
				Filter: <input type="text" name="filter" value="<?php echo htmlentities($filter); ?>" /> 
				<input type="submit" value="View" class="smallsubmit" />
			</form>
			</span>
		</div>
	</div>
	<div style="clear: both; padding-top: 20px;"></div>
<?php
	if ($numitems > 0)
	{
		$table->draw($result, $numitems, 95);
	}
?><br /><br />
	<div class="subblock">
		<div style="float:right;">
			Go to: <a href="<?php echo $g_options['scripturl'] . "?mode=playerinfo&amp;player=$player"; ?>"><?php echo $pl_name; ?>'s Statistics</a>
		</div>
	</div>
</div>
