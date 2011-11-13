<?php
/*
 * File: ratatoeskr/backend/main.php
 * Main file for the backend.
 * 
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/../sys/models.php");
require_once(dirname(__FILE__) . "/../sys/pwhash.php");
require_once(dirname(__FILE__) . "/../sys/textprocessors.php");
require_once(dirname(__FILE__) . "/../languages.php");

$admin_grp = Group::by_name("admins");

/* Mass creation of tags. */
function maketags($tagnames, $lang)
{
	$rv = array();
	foreach($tagnames as $tagname)
	{
		if(empty($tagname))
			continue;
		try
		{
			$tag = Tag::by_name($tagname);
		}
		catch(DoesNotExistError $e)
		{
			$tag = Tag::create($tagname);
			$tag->title[$lang] = new Translation($tagname, "");
		}
		$rv[] = $tag;
	}
	return $rv;
}

/* Generates Yes/No form / checks it. */
function askyesno($ste, $callback, $question, $yes=NULL, $no=NULL, $moredetails="")
{
	if(isset($_POST["yes"]))
		return True;
	if(isset($_POST["no"]))
		return False;
	
	$ste->vars["callback"] = $callback;
	$ste->vars["question"] = $question;
	if($yes !== NULL)
		$ste->vars["yestext"] = $yes;
	if($no !== NULL)
		$ste->vars["notext"] = $no;
	if($moredetails !== NULL)
		$ste->vars["moredetails"] = $moredetails;
	return $ste->exectemplate("systemtemplates/areyousure.html");
}

$backend_subactions = url_action_subactions(array(
	"_index" => url_action_alias(array("login")),
	"index" => url_action_alias(array("login")),
	/* _prelude guarantees that the user is logged in properly, so we do not have to care about that later, and sets some STE vars. */
	"_prelude" => function(&$data, $url_now, &$url_next)
	{
		global $ratatoeskr_settings, $admin_grp, $ste, $languages;
		
		$ste->vars["all_languages"] = array();
		foreach($languages as $code => $data)
			$ste->vars["all_languages"][$code] = $data["language"];
		ksort($ste->vars["all_languages"]);
		
		/* Check authentification */
		if(isset($_SESSION["ratatoeskr_uid"]))
		{
			try
			{
				$user = User::by_id($_SESSION["ratatoeskr_uid"]);
				if(($user->pwhash == $_SESSION["ratatoeskr_pwhash"]) and $user->member_of($admin_grp))
				{
					if(empty($user->language))
					{
						$user->language = $ratatoeskr_settings["default_language"];
						$user->save();
					}
					load_language($user->language);
					
					if($url_next[0] == "login")
						$url_next = array("content", "write");
					$data["user"] = $user;
					$ste->vars["user"] = array("name" => $user->username, "lang" => $user->language);
					
					return; /* Authentification successful, continue  */
				}
				else
					unset($_SESSION["ratatoeskr_uid"]);
			}
			catch(DoesNotExistError $e)
			{
				unset($_SESSION["uid"]);
			}
		}
		load_language();
		/* If we are here, user is not logged in... */
		$url_next = array("login");
	},
	"login" => url_action_simple(function($data)
	{
		global $ste, $admin_grp;
		if(!empty($_POST["user"]))
		{
			try
			{
				$user = User::by_name($_POST["user"]);
				if(!PasswordHash::validate($_POST["password"], $user->pwhash))
					throw new Exception();
				if(!$user->member_of($admin_grp))
					throw new Exception();
				
				/* Login successful. */
				$_SESSION["ratatoeskr_uid"]    = $user->get_id();
				$_SESSION["ratatoeskr_pwhash"] = $user->pwhash;
				$data["user"] = $user;
				$ste->vars["user"] = array("name" => $user->username, "lang" => $user->language);
			}
			catch(Exception $e)
			{
				$ste->vars["login_failed"] = True;
			}
			
			if(isset($data["user"]))
				throw new Redirect(array("content", "write"));
		}
		
		echo $ste->exectemplate("systemtemplates/backend_login.html");
	}),
	"logout" => url_action_simple(function($data)
	{
		echo "foo";
		unset($_SESSION["ratatoeskr_uid"]);
		unset($_SESSION["ratatoeskr_pwhash"]);
		throw new Redirect(array("login"));
	}),
	"content" => url_action_subactions(array(
		"write" => function(&$data, $url_now, &$url_next)
		{
			global $ste, $translation, $textprocessors, $ratatoeskr_settings, $languages;
			
			list($article, $editlang) = array_slice($url_next, 0);
			if(!isset($editlang))
				$editlang = $data["user"]->language;
			if(isset($article))
				$ste->vars["article_editurl"] = urlencode($article) . "/" . urlencode($editlang);
			else
				$ste->vars["article_editurl"] = "";
			
			$url_next = array();
			
			$default_section = Section::by_id($ratatoeskr_settings["default_section"]);
			
			$ste->vars["section"] = "content";
			$ste->vars["submenu"] = "newarticle";
			
			$ste->vars["textprocessors"] = array();
			foreach($textprocessors as $txtproc => $properties)
				if($properties[1])
					$ste->vars["textprocessors"][] = $txtproc;
			
			$ste->vars["sections"] = array();
			foreach(Section::all() as $section)
				$ste->vars["sections"][] = $section->name;
			$ste->vars["article_section"] = $default_section->name;
			
			/* Check Form */
			$fail_reasons = array();
			
			$inputs = array(
				"date" => time(),
				"article_status" => ARTICLE_STATUS_LIVE
			);
			
			if(isset($_POST["save_article"]))
			{
				if(!preg_match('/^[a-zA-Z0-9-_]+$/', @$_POST["urlname"]))
					$fail_reasons[] = $translation["invalid_urlname"];
				else
					$inputs["urlname"] = $_POST["urlname"];
				if((@$_POST["article_status"] < 0) or (@$_POST["article_status"] > 3))
					$fail_reasons[] = $translation["invalid_article_status"];
				else
					$inputs["article_status"] = (int) $_POST["article_status"];
				if(!isset($textprocessors[@$_POST["content_txtproc"]]))
					$fail_reasons[] = $translation["unknown_txtproc"];
				else
					$inputs["content_txtproc"] = $_POST["content_txtproc"];
				if(!isset($textprocessors[@$_POST["excerpt_txtproc"]]))
					$fail_reasons[] = $translation["unknown_txtproc"];
				else
					$inputs["excerpt_txtproc"] = $_POST["excerpt_txtproc"];
				if(!empty($_POST["date"]))
				{
					if(($time_tmp = strptime(@$_POST["date"], "%Y-%m-%d %H:%M:%S")) === False)
						$fail_reasons[] = $translation["invalid_date"];
					else
						$inputs["date"] = @mktime($time_tmp["tm_sec"], $time_tmp["tm_min"], $time_tmp["tm_hour"], $time_tmp["tm_mon"] + 1, $time_tmp["tm_mday"], $time_tmp["tm_year"] + 1900);
				}
				else
					$inputs["date"] = time();
				$inputs["allow_comments"] = !(empty($_POST["allow_comments"]) or $_POST["allow_comments"] != "yes");
				
				try
				{
					$inputs["section"] = Section::by_name($_POST["section"]);
				}
				catch(DoesNotExistError $e)
				{
					$fail_reasons[] = $translation["unknown_section"];
				}
				
				$inputs["title"]      = $_POST["title"];
				$inputs["content"]    = $_POST["content"];
				$inputs["excerpt"]    = $_POST["excerpt"];
				$inputs["tags"]       = array_filter(array_map("trim", explode(",", $_POST["tags"])), function($t) { return !empty($t); });
				if(isset($_POST["saveaslang"]))
					$editlang = $_POST["saveaslang"];
			}
			
			function fill_article(&$article, $inputs, $editlang)
			{
				$article->urlname   = $inputs["urlname"];
				$article->status    = $inputs["article_status"];
				$article->timestamp = $inputs["date"];
				$article->section   = $inputs["section"];
				$article->tags      = maketags($inputs["tags"], $editlang);
				$article->title  [$editlang] = new Translation($inputs["title"],   ""       );
				$article->text   [$editlang] = new Translation($inputs["content"], $inputs["content_txtproc"]);
				$article->excerpt[$editlang] = new Translation($inputs["excerpt"], $inputs["excerpt_txtproc"]);
			}
			
			if(empty($article))
			{
				/* New Article */
				$ste->vars["pagetitle"] = $translation["new_article"];
				
				if(empty($fail_reasons) and isset($_POST["save_article"]))
				{
					$article = Article::create($inputs["urlname"]);
					fill_article($article, $inputs, $editlang);
					try
					{
						$article->save();
						$ste->vars["article_editurl"] = urlencode($article->urlname) . "/" . urlencode($editlang);
						$ste->vars["success"] = htmlesc($translation["article_save_success"]);
					}
					catch(AlreadyExistsError $e)
					{
						$fail_reasons[] = $translation["article_name_already_in_use"];
					}
				}
			}
			else
			{
				try
				{
					$article = Article::by_urlname($article);
				}
				catch(DoesNotExistError $e)
				{
					throw new NotFoundError();
				}
				
				if(empty($fail_reasons) and isset($_POST["save_article"]))
				{
					fill_article($article, $inputs, $editlang);
					try
					{
						$article->save();
						$ste->vars["article_editurl"] = urlencode($article->urlname) . "/" . urlencode($editlang);
						$ste->vars["success"] = htmlesc($translation["article_save_success"]);
					}
					catch(AlreadyExistsError $e)
					{
						$fail_reasons[] = $translation["article_name_already_in_use"];
					}
				}
				
				foreach(array(
					"urlname"        => "urlname",
					"section"        => "article_section",
					"status"         => "article_status",
					"timestamp"      => "date",
					"allow_comments" => "allow_comments"
				) as $prop => $k_out)
				{
					if(!isset($inputs[$k_out]))
						$inputs[$k_out] = $article->$prop;
				}
				if(!isset($inputs["title"]))
					$inputs["title"] = $article->title[$editlang]->text;
				if(!isset($inputs["content"]))
				{
					$translation_obj           = $article->text[$editlang];
					$inputs["content"]         = $translation_obj->text;
					$inputs["content_txtproc"] = $translation_obj->texttype;
				}
				if(!isset($inputs["excerpt"]))
				{
					$translation_obj           = $article->excerpt[$editlang];
					$inputs["excerpt"]         = $translation_obj->text;
					$inputs["excerpt_txtproc"] = $translation_obj->texttype;
				}
				if(!isset($inputs["tags"]))
					$inputs["tags"] = array_map(function($tag) use ($editlang) { return $tag->name; }, $article->tags);
				$ste->vars["morelangs"] = array();
				$ste->vars["pagetitle"] = $article->title[$editlang]->text;
				foreach($article->title as $lang => $_)
				{
					if($lang != $editlang)
						$ste->vars["morelangs"][] = array("url" => urlencode($article->urlname) . "/$lang", "full" => "($lang) " . $languages[$lang]["language"]);
				}
			}
			
			/* Push data back to template */
			if(isset($inputs["tags"]))
				$ste->vars["tags"] = implode(", ", $inputs["tags"]);
			if(isset($inputs["article_section"]))
				$ste->section["article_section"] = $inputs["article_section"]->name;
			$ste->vars["editlang"] = $editlang;
			foreach(array(
				"urlname"         => "urlname",
				"article_status"  => "article_status",
				"title"           => "title",
				"content_txtproc" => "content_txtproc",
				"content"         => "content",
				"excerpt_txtproc" => "excerpt_txtproc",
				"excerpt"         => "excerpt",
				"date"            => "date",
				"allow_comments"  => "allow_comments"
			) as $k_in => $k_out)
			{
				if(isset($inputs[$k_in]))
					$ste->vars[$k_out] = $inputs[$k_in];
			}
			
			if(!empty($fail_reasons))
				$ste->vars["failed"] = $fail_reasons;
			
			echo $ste->exectemplate("systemtemplates/content_write.html");
		},
		"tags" => function(&$data, $url_now, &$url_next)
		{
			global $translation, $languages, $ste, $rel_path_to_root;
			$ste->vars["section"] = "content";
			$ste->vars["submenu"] = "tags";
			
			list($tagname, $tagaction) = $url_next;
			$url_next = array();
			
			if(isset($tagname))
			{
				try
				{
					$tag = Tag::by_name($tagname);
				}
				catch(DoesNotExistError $e)
				{
					throw new NotFoundError();
				}
				
				if(isset($tagaction))
				{
					switch($tagaction)
					{
						case "delete": 
							$ste->vars["pagetitle"] = str_replace("[[TAGNAME]]", $tag->name, $translation["delete_tag_pagetitle"]);
							$yesnoresp = askyesno($ste, "$rel_path_to_root/backend/content/tags/{$tag->name}/delete", $translation["delete_comment_question"]);
							if(is_string($yesnoresp))
							{
								echo $yesnoresp;
								return;
							}
					
							if($yesnoresp)
							{
								$tag->delete();
								echo $ste->exectemplate("systemtemplates/tag_deleted.html");
							}
							else
								goto backend_content_tags_overview; /* Hopefully no dinosaur will attack me: http://xkcd.com/292/ :-) */
							break;
						case "addtranslation":
							$ste->vars["pagetitle"] = $translation["tag_add_lang"];
							$ste->vars["tagname"] = $tag->name;
							if(isset($_POST["addtranslation"]))
							{
								$errors = array();
								if(!isset($languages[@$_POST["language"]]))
									$errors[] = $translation["language_unknown"];
								if(empty($_POST["translation"]))
									$errors[] = $translation["no_translation_text_given"];
								if(empty($errors))
								{
									$tag->title[$_POST["language"]] = new Translation($_POST["translation"], "");
									$tag->save();
									$ste->vars["success"] = $translation["tag_translation_added"];
									goto backend_content_tags_overview;
								}
								else
									$ste->vars["errors"] = $errors;
							}
							echo $ste->exectemplate("systemtemplates/tag_addtranslation.html");
							break;
					}
				}
			}
			else
			{
				backend_content_tags_overview:
				
				if(isset($_POST["create_new_tag"]))
				{
					if((strpos(@$_POST["new_tag_name"], ",") !== False) or (strpos(@$_POST["new_tag_name"], " ") !== False) or (strlen(@$_POST["new_tag_name"]) == 0))
						$ste->vars["error"] = $translation["invalid_tag_name"];
					else
					{
						try
						{
							$tag = Tag::create($_POST["new_tag_name"]);
							$tag->title[$data["user"]->language] = new Translation($_POST["new_tag_name"], "");
							$tag->save();
							$ste->vars["success"] = $translation["tag_created_successfully"];
						}
						catch(AlreadyExistsError $e)
						{
							$ste->vars["error"] = $translation["tag_name_already_in_use"];
						}
					}
				}
				
				if(isset($_POST["edit_translations"]))
				{
					$tagbuffer = array();
					foreach($_POST as $k => $v)
					{
						if(preg_match("/^tagtrans_(.*?)_(.*)$/", $k, $matches))
						{
							if(!isset($languages[$matches[1]]))
								continue;
							
							if(!isset($tagbuffer[$matches[2]]))
							{
								try
								{
									$tagbuffer[$matches[2]] = Tag::by_name($matches[2]);
								}
								catch(DoesNotExistError $e)
								{
									continue;
								}
							}
							
							if(empty($v) and isset($tagbuffer[$matches[2]]->title[$matches[1]]))
								unset($tagbuffer[$matches[2]]->title[$matches[1]]);
							elseif(empty($v))
								continue;
							else
								$tagbuffer[$matches[2]]->title[$matches[1]] = new Translation($v, "");
						}
					}
					
					foreach($tagbuffer as $tag)
						$tag->save();
					
					$ste->vars["success"] = $translation["tag_titles_edited_successfully"];
				}
				
				$ste->vars["pagetitle"] = $translation["tags_overview"];
				
				$alltags = Tag::all();
				usort($alltags, function($a, $b) { return strcmp($a->name, $b->name); });
				$ste->vars["all_tag_langs"] = array();
				$ste->vars["alltags"] = array();
				foreach($alltags as $tag)
				{
					$tag_pre = array("name" => $tag->name, "translations" => array());
					foreach($tag->title as $langcode => $translation_obj)
					{
						$tag_pre["translations"][$langcode] = $translation_obj->text;
						if(!isset($ste->vars["all_tag_langs"][$langcode]))
							$ste->vars["all_tag_langs"][$langcode] = $languages[$langcode]["language"];
					}
					$ste->vars["alltags"][] = $tag_pre;
				}
				echo $ste->exectemplate("systemtemplates/tags_overview.html");
			}
		},
		"articles" => function(&$data, $url_now, &$url_next)
		{
			global $ste, $translation, $languages, $rel_path_to_root;
			
			$url_next = array();
			
			$ste->vars["section"]   = "content";
			$ste->vars["submenu"]   = "articles";
			$ste->vars["pagetitle"] = $translation["menu_articles"];
			
			if(isset($_POST["delete"]) and ($_POST["really_delete"] == "yes"))
			{
				foreach($_POST["article_multiselect"] as $article_urlname)
				{
					try
					{
						$article = Article::by_urlname($article_urlname);
						$article->delete();
					}
					catch(DoesNotExistError $e)
					{
						continue;
					}
				}
				
				$ste->vars["success"] = $translation["articles_deleted"];
			}
			
			$articles = Article::all();
			
			/* Filtering */
			$filterquery = array();
			if(!empty($_GET["filter_urlname"]))
			{
				$searchfor = strtolower($_GET["filter_urlname"]);
				$articles = array_filter($articles, function($a) use ($searchfor) { return strpos(strtolower($a->urlname), $searchfor) !== False; });
				$filterquery[] = "filter_urlname=" . urlencode($_GET["filter_urlname"]);
				$ste->vars["filter_urlname"] = $_GET["filter_urlname"];
			}
			if(!empty($_GET["filter_tag"]))
			{
				$searchfor = $_GET["filter_tag"];
				$articles = array_filter($articles, function($a) use ($searchfor) { foreach($a->tags as $t) { if($t->name==$searchfor) return True; } return False; });
				$filterquery[] = "filter_tag=" . urlencode($searchfor);
				$ste->vars["filter_tag"] = $searchfor;
			}
			if(!empty($_GET["filter_section"]))
			{
				$searchfor = $_GET["filter_section"];
				$articles = array_filter($articles, function($a) use ($searchfor) { return $a->section->name == $searchfor; });
				$filterquery[] = "filter_section=" . urlencode($searchfor);
				$ste->vars["filter_section"] = $searchfor;
			}
			$ste->vars["filterquery"] = implode("&", $filterquery);
			
			/* Sorting */
			if(isset($_GET["sort_asc"]))
			{
				switch($_GET["sort_asc"])
				{
					case "date":
						$ste->vars["sortquery"] = "sort_asc=date";
						$ste->vars["sort_asc_date"] = True;
						$ste->vars["sorting"] = array("dir" => "asc", "by" => "date");
						usort($articles, function($a, $b) { return intcmp($a->timestamp, $b->timestamp); });
						break;
					case "section":
						$ste->vars["sortquery"] = "sort_asc=section";
						$ste->vars["sort_asc_section"] = True;
						$ste->vars["sorting"] = array("dir" => "asc", "by" => "section");
						usort($articles, function($a, $b) { return strcmp($a->section->name, $b->section->name); });
						break;
					case "urlname":
						$ste->vars["sortquery"] = "sort_asc=urlname";
					default:
						$ste->vars["sort_asc_urlname"] = True;
						$ste->vars["sorting"] = array("dir" => "asc", "by" => "urlname");
						usort($articles, function($a, $b) { return strcmp($a->urlname, $b->urlname); });
						break;
				}
			}
			elseif(isset($_GET["sort_desc"]))
			{
				switch($_GET["sort_desc"])
				{
					case "date":
						$ste->vars["sortquery"] = "sort_desc=date";
						$ste->vars["sort_desc_date"] = True;
						$ste->vars["sorting"] = array("dir" => "desc", "by" => "date");
						usort($articles, function($a, $b) { return intcmp($b->timestamp, $a->timestamp); });
						break;
					case "section":
						$ste->vars["sortquery"] = "sort_desc=section";
						$ste->vars["sort_desc_section"] = True;
						$ste->vars["sorting"] = array("dir" => "desc", "by" => "section");
						usort($articles, function($a, $b) { return strcmp($b->section->name, $a->section->name); });
						break;
					case "urlname":
						$ste->vars["sortquery"] = "sort_desc=urlname";
						$ste->vars["sort_desc_urlname"] = True;
						$ste->vars["sorting"] = array("dir" => "desc", "by" => "urlname");
						usort($articles, function($a, $b) { return strcmp($b->urlname, $a->urlname); });
						break;
					default:
						$ste->vars["sort_asc_urlname"] = True;
						$ste->vars["sorting"] = array("dir" => "asc", "by" => "urlname");
						usort($articles, function($a, $b) { return strcmp($a->urlname, $b->urlname); });
						break;
				}
			}
			else
			{
				$ste->vars["sort_asc_urlname"] = True;
				$ste->vars["sorting"] = array("dir" => "asc", "by" => "urlname");
				usort($articles, function($a, $b) { return strcmp($a->urlname, $b->urlname); });
			}
			
			$ste->vars["articles"] = array_map(function($article) {
				$avail_langs = array();
				foreach($article->title as $lang => $_)
					$avail_langs[] = $lang;
				sort($avail_langs);
				return array(
					"urlname"   => $article->urlname,
					"languages" => $avail_langs,
					"date"      => $article->timestamp,
					"tags"      => array_map(function($tag) { return $tag->name; }, $article->tags),
					"section"   => array("id" => $article->section->get_id(), "name" => $article->section->name)
				);
			}, $articles);
			
			echo $ste->exectemplate("systemtemplates/articles.html");
		}
	))
));

?>
