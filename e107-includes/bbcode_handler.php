<?php
/*
+ ----------------------------------------------------------------------------+
|     e107 website system
|
|     Steve Dunstan 2001-2002
|     Copyright (C) 2008-2010 e107 Inc (e107.org)
|
|
|     Released under the terms and conditions of the
|     GNU General Public License (http://gnu.org).
|
|     $URL: https://e107.svn.sourceforge.net/svnroot/e107/trunk/e107_0.7/e107_handlers/bbcode_handler.php $
|     $Revision: 12112 $
|     $Id: bbcode_handler.php 12112 2011-03-21 11:57:50Z secretr $
|     $Author: secretr $
+----------------------------------------------------------------------------+
*/

if (!defined('e107_INIT')) { exit; }

class e_bbcode
{
	var $bbList;			// Caches the file contents for each bbcode processed
	var $bbLocation;		// Location for each file - 'core' or a plugin name
	var $preProcess = FALSE;	// Set when processing bbcodes prior to saving

	function e_bbcode()
	{
		global $pref;
		$core_bb = array(
		'blockquote', 'img', 'i', 'u', 'center',
		'_br', 'color', 'size', 'code',
		'html', 'flash', 'link', 'email',
		'url', 'quote', 'left', 'right',
		'b', 'justify', 'file', 'stream',
		'textarea', 'list', 'php', 'time',
		'spoiler', 'hide', 'youtube', 'sanitised'
		);

		foreach($core_bb as $c)
		{
			$this->bbLocation[$c] = 'core';
		}

		// grab list of plugin bbcodes.
		if(isset($pref['bbcode_list']) && $pref['bbcode_list'] != '')
		{
			foreach($pref['bbcode_list'] as $path=>$namearray)
			{
				foreach($namearray as $code=>$uclass)
				{
					$this->bbLocation[$code] = $path;
				}
			}
		}

		// Eliminate duplicates
		$this->bbLocation = array_diff($this->bbLocation, array(''));
		krsort($this->bbLocation);
	}

	// If $bbStrip is TRUE (boolean or word), all bbcodes are stripped. If FALSE, none are stripped.
	// If a comma separated (lower case) list is passed, only the listed codes are stripped (and the rest are processed)
	// If the first word of $bbStrip is 'PRE', sets pre-save mode. Any other parameters follow, comma separated
	function parseBBCodes($value, $p_ID, $force_lower = 'default', $bbStrip = FALSE)
	{
		global $postID;
		$postID = $p_ID;


		if (strlen($value) <= 6) return $value;   				// Don't waste time on trivia!
		if ($force_lower == 'default') $force_lower = TRUE;		// Set the default behaviour if not overridden
		$code_stack = array();									// Stack for unprocessed bbcodes and text
		$unmatch_stack = array();								// Stack for unmatched bbcodes
		$result = '';											// Accumulates fully processed text
		$stacktext = '';										// Accumulates text which might be subject to one or more bbcodes
		$nopro = FALSE;											// Blocks processing within [code]...[/code] tags
		$this->preProcess = FALSE;

		$strip_array = array();
		if (!is_bool($bbStrip))
		{
			$strip_array = explode(',',$bbStrip);
			if ($strip_array[0] == 'PRE')
			{
				$this->preProcess = TRUE;
				unset($strip_array[0]);
				if (count($strip_array) == 0)
				{
					$bbStrip = FALSE;
				}
				elseif (in_array('TRUE', $strip_array))
				{
					$bbStrip = TRUE;
				}

			}
		}
		$pattern = '#^\[(/?)([A-Za-z_]+)(\d*)([=:]?)(.*?)]$#i';	// Pattern to split up bbcodes
		// $matches[0] - same as the input text
		// $matches[1] - '/' for a closing tag. Otherwise empty string
		// $matches[2] - the bbcode word
		// $matches[3] - any digits immediately following the bbcode word
		// $matches[4] - '=' or ':' according to the separator used
		// $matches[5] - any parameter

		$content = preg_split('#(\[(?:\w|/\w).*?\])#mis', $value, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );

		foreach ($content as $cont)
		{  // Each chunk is either a bbcode or a piece of text
			$is_proc = FALSE;
			while (!$is_proc)
			{
				$oddtext = '';
				if ($cont[0] == '[')
				{  // We've got a bbcode - split it up and process it
					$match_count = preg_match($pattern,$cont,$matches);
					$bbparam = (isset($matches[5])) ? $matches[5] : '';
					$bbword = (isset($matches[2])) ? $matches[2] : '';
					if($cont[1] != '/')
					{
						$bbsep = varset($matches[4]);
					}
					if ($force_lower) $bbword = strtolower($bbword);
					if ($nopro && ($bbword == 'code') && ($matches[1] == '/')) $nopro = FALSE;		// End of code block
					if (($bbword) && ($bbword == trim($bbword)) && !$nopro)
					{  // Got a code to process here
						if (($bbStrip === TRUE) || in_array($bbword,$strip_array))
						{
							$is_proc = TRUE;		// Just discard this bbcode
						}
						else
						{

							if ($matches[1] == '/')
							{  // Closing code to process
								$found = FALSE;
								$i = 0;
								while ($i < count($code_stack))
								{     // See if code is anywhere on the stack.
									if (($code_stack[$i]['type'] == 'bbcode') && ($code_stack[$i]['code'] == $bbword) && ($code_stack[0]['numbers'] == $matches[3]))
									{
										$found = TRUE;
										break;
									}
									$i++;
								}

								if ($found)
								{
									$found = FALSE;   // Use as 'done' variable now
									// Code is on stack - $i has index number. Process text, discard unmatched open codes, process 'our' code
									while ($i > 0) { $unmatch_stack[] = array_shift($code_stack); $i--; }    // Get required code to top of stack

									// Pull it off using array_shift - this keeps it as a zero-based array, newest first.
									while (!$found && (count($code_stack) != 0))
									{
										switch ($code_stack[0]['type'])
										{
											case 'text' :
												$stacktext = $code_stack[0]['code'].$stacktext;   // Need to insert text at front
												array_shift($code_stack);
												break;
											case 'bbcode' :
												if (($code_stack[0]['code'] == $bbword) && ($code_stack[0]['numbers'] == $matches[3]))
												{
													$stacktext = $this->proc_bbcode($bbword,$code_stack[0]['param'],$stacktext,$bbparam, $code_stack[0]['bbsep'], $code_stack[0]['block'].$stacktext.$cont);
													array_shift($code_stack);
													// Intentionally don't terminate here - may be some text we can clean up
													$bbword='';    // Necessary to make sure we don't double process if several instances on stack
													while (count($unmatch_stack) != 0) { array_unshift($code_stack,array_pop($unmatch_stack));  }
												}
												else
												{
													$found = TRUE;  // Terminate on unmatched bbcode
												}
												break;
										}
										if (count($code_stack) == 0)
										{
											$result .= $stacktext;
											$stacktext = '';
											$found = TRUE;
										}
									}
									$is_proc = TRUE;
								}
							}
							else
							{  // Opening code to process
								// If its a single code, we can process it now. Otherwise just stack the value
								if (array_key_exists('_'.$bbword,$this->bbLocation))
								{  // Single code to process
									if (count($code_stack) == 0)
									{
										$result .= $this->proc_bbcode('_'.$bbword,$bbparam,'','','',$cont);
									}
									else
									{
										$stacktext .= $this->proc_bbcode('_'.$bbword,$bbparam,'','','',$cont);
									}
									$is_proc = TRUE;
								}
								elseif (array_key_exists($bbword,$this->bbLocation))
								{
									if ($stacktext != '')
									{ // Stack the text we've accumulated so far
										array_unshift($code_stack,array('type' => 'text','code' => $stacktext));
										$stacktext = '';
									}
									array_unshift($code_stack,array('type' => 'bbcode','code' => $bbword, 'numbers'=> $matches[3], 'param'=>$bbparam, 'bbsep' => $bbsep, 'block' => $cont));
									if ($bbword == 'code') $nopro = TRUE;
									$is_proc = TRUE;
								}
							}
						}
					}
					// Next lines could be deleted - but gives better rejection of 'stray' opening brackets
					if ((!$is_proc) && (($temp = strrpos($cont,"[")) !== 0))
					{
						$oddtext = substr($cont,0,$temp);
						$cont = substr($cont,$temp);
					}
				}

				if (!$is_proc)
				{  // We've got some text between bbcodes (or possibly text in front of a bbcode)
					if ($oddtext == '') { $oddtext = $cont; $is_proc = TRUE; }
					if (count($code_stack) == 0)
					{  // Can just add text to answer
						$result .= $oddtext;
					}
					else
					{  // Add to accumulator at this level
						$stacktext .= $oddtext;
					}
				}
			}
		}

		// Basically done - just tidy up now
		// If there's still anything on the stack, we need to process it
		while (count($code_stack) != 0)
		{
			switch ($code_stack[0]['type'])
			{
				case 'text' :
					$stacktext = $code_stack[0]['code'].$stacktext;   // Need to insert text at front
					array_shift($code_stack);
					break;
				case 'bbcode' :
					$stacktext = '['.$code_stack[0]['code'].']'.$stacktext;   // To discard unmatched codes, delete this line
					array_shift($code_stack);  		// Just discard any unmatched bbcodes
					break;
			}
		}
		$result .= $stacktext;
		return $result;
	}

	/**
	 * Filter bbcode Input
	 * @param string $input [optional]
	 * @return string
	 */
	function filter($input='')
	{
		if(!$input)
		{
			return;
		}

		// filter is breaking bbcodes with - input is modified with strtolower()
		//$input = strtolower($input);
		//$search = array('document.cookie','location.href','onload');
		//return str_replace($search,"",$input);

		// a quick fix attempt - basic filtering
		return preg_replace('/(document\.cookie|location\.href|onload)/i', '', $input);
	}


	/**
	 * Invoke the actual bbcode handler
	 * @param string $code - textual value of the bbcode (already begins with '_' if a single code)
	 * @param string $param1 - any text after '=' in the opening code
	 * @param string $code_text_par - text between the opening and closing codes
	 * @param string $param2 - any text after '=' for the closing code
	 * @param string $sep - character separating bbcode name and any parameters
	 * @param string $full_text - the 'raw' text between, and including, the opening and closing bbcode tags
	 * @return
	 */
	function proc_bbcode($code, $param1='',$code_text_par='', $param2='', $sep='', $full_text='')
	{
		global $tp, $postID, $code_text, $parm;
		$parm = $this->filter($param1);

		$code_text = $code_text_par;

		if (E107_DEBUG_LEVEL)
		{
			global $db_debug;
			$db_debug->logCode(1, $code, $parm, $postID);
		}

		if (is_array($this->bbList) && array_key_exists($code, $this->bbList))
		{	// Check the bbcode 'cache'
			$bbcode = $this->bbList[$code];
		}
		else
		{	// Find the file
			if ($this->bbLocation[$code] == 'core')
			{
				$bbPath = e_FILE.'bbcode/';
				$bbFile = strtolower(str_replace('_', '', $code));
			}
			else
			{	// Add code to check for plugin bbcode addition
				$bbPath = e_PLUGIN.$this->bbLocation[$code].'/';
				$bbFile = strtolower($code);
			}
			if (file_exists($bbPath.'bb_'.$bbFile.'.php'))
			{	// Its a bbcode class file
				require_once($bbPath.'bb_'.$bbFile.'.php');
				//echo "Load: {$bbFile}.php<br />";
				$className = 'bb_'.$code;
				$this->bbList[$code] = new $className();
			}
			elseif (file_exists($bbPath.$bbFile.'.bb'))
			{
				$bbcode = file_get_contents($bbPath.$bbFile.'.bb');
				$this->bbList[$code] = $bbcode;
			}
			else
			{
				$this->bbList[$code] = '';
				return false;
			}
		}
		global $e107_debug;

		if (is_object($this->bbList[$code]))
		{
			if ($this->preProcess)
			{
				return $this->bbList[$code]->bbPreSave($code_text, $param1);
			}
			return $this->bbList[$code]->bbPreDisplay($code_text, $param1);
		}
		if ($this->preProcess) return $full_text;		// No change

		/**
		 *	@todo - capturing output deprecated
		 */
		ob_start();
		$bbcode_return = eval($bbcode);
		$bbcode_output = ob_get_contents();
		ob_end_clean();

		/* added to remove possibility of nested bbcode exploits ... */
		if(strpos($bbcode_return, "[") !== FALSE)
		{
			$exp_search = array("eval", "expression");
			$exp_replace = array("ev<b></b>al", "expres<b></b>sion");
			$bbcode_return = str_replace($exp_search, $exp_replace, $bbcode_return);
		}
		return $bbcode_output.$bbcode_return;
	}
}



/**
 *	Base class for bbcode handlers
 *
 *	Contains core routines for entry, security, logging....
 */
class e_bb_base
{
	/**
	 *	Constructor
	 */
	function e_bb_base()
	{
	}



	/**
	 *	Called prior to save of user-entered text
	 *
	 *	Allows initial parsing of bbcode, including the possibility of removing or transforming the enclosed text (as is done by the youtube processing)
	 *
	 *	This is the 'new' facility
	 *
	 *	@todo - make 'final' for PHP5
	 */
	function bbPreSave(&$code_text, &$parm)
	{
		// Could add logging, security in here
		return $this->toDB($code_text, $parm);
	}



	/**
	 *	Process bbcode prior to display
	 *
	 *	Functionally this routine does exactly the same as the existing bbcodes
	 *
	 *	@todo - make 'final' for PHP5
	 */
	function bbPreDisplay(&$code_text, &$parm)
	{
		// Could add logging, security in here
		return $this->toHTML($code_text, $parm);
	}
}

?>