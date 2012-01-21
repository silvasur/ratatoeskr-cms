<?php

/* Check some files/directories before continue.... */

$dirs = array(
	"/ratatoeskr"                               => False,
	"/ratatoeskr/templates"                     => False,
	"/ratatoeskr/templates/transc"              => True,
	"/ratatoeskr/templates/src"                 => False,
	"/ratatoeskr/templates/src/usertemplates"   => True,
	"/ratatoeskr/templates/src/plugintemplates" => True,
	"/ratatoeskr/templates/src/systemtemplates" => False,
	"/ratatoeskr/translations"                  => False,
	"/ratatoeskr/licenses"                      => False,
	"/ratatoeskr/libs"                          => False,
	"/ratatoeskr/sys"                           => False,
	"/ratatoeskr/setup"                         => False,
	"/ratatoeskr/cms_style"                     => False,
	"/ratatoeskr/cms_style/images"              => False,
	"/ratatoeskr/plugin_extradata"              => True,
	"/ratatoeskr/plugin_extradata/public"       => True,
	"/ratatoeskr/plugin_extradata/private"      => True,
	"/images"                                   => True,
	"/images/previews"                          => True
);
$files = array(
	"/.htaccess",
	"/ratatoeskr/templates/src/systemtemplates/users.html",
	"/ratatoeskr/templates/src/systemtemplates/pluginlist.html",
	"/ratatoeskr/templates/src/systemtemplates/backend_login.html",
	"/ratatoeskr/templates/src/systemtemplates/confirminstall.html",
	"/ratatoeskr/templates/src/systemtemplates/pluginhelp.html",
	"/ratatoeskr/templates/src/systemtemplates/tag_addtranslation.html",
	"/ratatoeskr/templates/src/systemtemplates/settings.html",
	"/ratatoeskr/templates/src/systemtemplates/tag_deleted.html",
	"/ratatoeskr/templates/src/systemtemplates/areyousure.html",
	"/ratatoeskr/templates/src/systemtemplates/articles.html",
	"/ratatoeskr/templates/src/systemtemplates/image_list.html",
	"/ratatoeskr/templates/src/systemtemplates/repos.html",
	"/ratatoeskr/templates/src/systemtemplates/templates.html",
	"/ratatoeskr/templates/src/systemtemplates/error.html",
	"/ratatoeskr/templates/src/systemtemplates/instant_select.tpl",
	"/ratatoeskr/templates/src/systemtemplates/plugininstall.html",
	"/ratatoeskr/templates/src/systemtemplates/comments_list.html",
	"/ratatoeskr/templates/src/systemtemplates/tags_overview.html",
	"/ratatoeskr/templates/src/systemtemplates/sections.html",
	"/ratatoeskr/templates/src/systemtemplates/image_embed.html",
	"/ratatoeskr/templates/src/systemtemplates/master.html",
	"/ratatoeskr/templates/src/systemtemplates/content_write.html",
	"/ratatoeskr/templates/src/systemtemplates/user.html",
	"/ratatoeskr/templates/src/systemtemplates/single_comment.html",
	"/ratatoeskr/templates/src/systemtemplates/setup_dbsetup.html",
	"/ratatoeskr/templates/src/systemtemplates/setup_done.html",
	"/ratatoeskr/templates/src/systemtemplates/setup_master.html",
	"/ratatoeskr/templates/src/systemtemplates/setup_select_lang.html",
	"/ratatoeskr/templates/src/usertemplates/master.html",
	"/ratatoeskr/templates/src/usertemplates/standard.html",
	"/ratatoeskr/templates/src/usertemplates/some_useful_tags",
	"/ratatoeskr/templates/.htaccess",
	"/ratatoeskr/translations/de.php",
	"/ratatoeskr/translations/en.php",
	"/ratatoeskr/backend.php",
	"/ratatoeskr/libs/markdown.php",
	"/ratatoeskr/libs/stupid_template_engine.php",
	"/ratatoeskr/libs/kses.php",
	"/ratatoeskr/.htaccess",
	"/ratatoeskr/setup/create_tables.php",
	"/ratatoeskr/setup/setup.php",
	"/ratatoeskr/sys/plugin_api.php",
	"/ratatoeskr/sys/translation.php",
	"/ratatoeskr/sys/urlprocess.php",
	"/ratatoeskr/sys/pluginpackage.php",
	"/ratatoeskr/sys/db.php",
	"/ratatoeskr/sys/utils.php",
	"/ratatoeskr/sys/pwhash.php",
	"/ratatoeskr/sys/default_settings.php",
	"/ratatoeskr/sys/init_ste.php",
	"/ratatoeskr/sys/models.php",
	"/ratatoeskr/sys/textprocessors.php",
	"/ratatoeskr/languages.php",
	"/ratatoeskr/frontend.php",
	"/ratatoeskr/cms_style/layout.css",
	"/ratatoeskr/cms_style/login.css",
	"/ratatoeskr/cms_style/images/add.png",
	"/ratatoeskr/cms_style/images/delete.png",
	"/ratatoeskr/cms_style/images/sortarrow_up_outline.png",
	"/ratatoeskr/cms_style/images/black_transparent.png",
	"/ratatoeskr/cms_style/images/sortarrow_down_outline.png",
	"/ratatoeskr/cms_style/images/success.png",
	"/ratatoeskr/cms_style/images/dead_emoticon.png",
	"/ratatoeskr/cms_style/images/sortarrow_up_filled.png",
	"/ratatoeskr/cms_style/images/sortarrow_down_filled.png",
	"/ratatoeskr/cms_style/images/notice.png",
	"/ratatoeskr/cms_style/images/login_bg.jpg",
	"/ratatoeskr/cms_style/images/error.png",
	"/ratatoeskr/main.php",
	"/ratatoeskr/plugin_extradata/private/.htaccess",
	"/session_doctor.php",
	"/index.php",
	"/setup.php",
	"/css.php"
);

$missing_files = array();
$missing_dirs = array();
$missing_perms = array();

foreach($dirs as $dir => $needs_w_perms)
{
	if(!is_dir(dirname(__FILE__) . $dir))
		$missing_dirs[] = $dir;
	elseif($needs_w_perms and (!@is_writable(dirname(__FILE__) . $dir)))
		$missing_perms[] = $dir;
}

foreach($files as $file)
{
	if(!is_file(dirname(__FILE__) . $file))
		$missing_files[] = $file;
}

/* Also check for the correct PHP version, some PHP extensions and if we are running on apache. */
$missing_requirements = array();

if(!defined('PHP_VERSION_ID'))
{
	$version = explode('.', PHP_VERSION);
	define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}

if(PHP_VERSION_ID < 50300)
	$missing_requirements[] = "You need PHP version 5.3.0 or later.";

$available_extensions = get_loaded_extensions();

if(strpos($_SERVER["SERVER_SOFTWARE"], "Apache") === False)
	$missing_requirements[] = "You need an Apache WWW server for Ratatöskr.";

if(!in_array("gd", $available_extensions))
	$missing_requirements[] = "You need the gd PHP extension.";
if(!in_array("session", $available_extensions))
	$missing_requirements[] = "You need the session PHP extension.";
if(!in_array("mysql", $available_extensions))
	$missing_requirements[] = "You need the mysql PHP extension.";

if(!in_array("hash", $available_extensions))
	$missing_requirements[] = "You need the hash PHP extension.";
elseif(!in_array("sha1", hash_algos()))
	$missing_requirements[] = "The SHA1 hash algorythm must be available.";

if((!empty($missing_dirs)) or (!empty($missing_files)) or (!empty($missing_perms)) or (!empty($missing_requirements))):
?>
<html>
<head>
	<title>Ratatöskr installer</title>
</head>
<body>
	<h1>Ratatöskr can not be installed, because...</h1>
	<?php if(!empty($missing_requirements)): ?>
	
		<h2>...these requirements are not met:</h2>
		<ul>
			<?php foreach($missing_requirements as $req): ?>
				<li><?php echo htmlspecialchars($req); ?></li>
			<?php endforeach; ?>
		</ul>
	
	<?php endif; if(!empty($missing_dirs)): ?>
		
		<h2>...these directories are missing:</h2>
		<ul>
			<?php foreach($missing_dirs as $dir): ?>
				<li><?php echo htmlspecialchars($dir); ?></li>
			<?php endforeach; ?>
		</ul>
		
	<?php endif; if(!empty($missing_files)): ?>
		
		<h2>...these files are missing:</h2>
		<ul>
			<?php foreach($missing_files as $file): ?>
				<li><?php echo htmlspecialchars($file); ?></li>
			<?php endforeach; ?>
		</ul>
		
	<?php endif; if(!empty($missing_perms)): ?>
		
		<h2>...we do not have writing permissions to these directories:</h2>
		<ul>
			<?php foreach($missing_perms as $dir): ?>
				<li><?php echo htmlspecialchars($dir); ?></li>
			<?php endforeach; ?>
		</ul>
		
	<?php endif; ?>
</body>
</html>
<?php
die();
endif;

require_once(dirname(__FILE__) . "/ratatoeskr/setup/setup.php");

?>
