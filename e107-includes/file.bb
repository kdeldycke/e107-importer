global $pref, $fromadmin;

if ((e_PAGE == 'page.php') || (array_key_exists('forum_attach', $pref) && $pref['forum_attach'] && FILE_UPLOADS || ADMIN || $fromadmin))
{
	$image = (file_exists(THEME."images/file.png") ? THEME."images/file.png" : e_IMAGE."generic/".IMODE."/file.png");
	list($fname, $uc) = explode("^", $parm."^");
	if($uc)
	{
		if(!check_class($uc))
		{
			return;
		}
	}
	return "<a href='".$tp -> toAttribute($fname)."'><img src='".$image."' alt='' style='border:0; vertical-align:middle' /></a> <a href='".$tp -> toAttribute($fname)."'>".$code_text."</a>";
}
