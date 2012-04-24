<?php
/*
 * File: ratatoeskr/frontend.php
 * All the stuff for the frontend (i.e. what the visitor of the website sees).
 * 
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/sys/utils.php");
require_once(dirname(__FILE__) . "/languages.php");
require_once(dirname(__FILE__) . "/sys/models.php");
require_once(dirname(__FILE__) . "/sys/textprocessors.php");
require_once(dirname(__FILE__) . "/libs/kses.php");

/*
 * Function: section_transform_ste
 * Transforms an <Section> object to an array, so it can be accessed via a STE template.
 * 
 * Parameters:
 * 	$section - <Section> object.
 * 	$lang - The current language.
 * 
 * Returns:
 * 	Array with these fields:
 * 	* id
 * 	* name
 * 	* title
 */
function section_transform_ste($section, $lang)
{
	return array(
		"id"    => $section->get_id(),
		"name"  => $section->name,
		"title" => $section->title[$lang]->text,
		"__obj" => $section
	);
}

/*
 * Function: tag_transform_ste
 * Transforms an <Tag> object to an array, so it can be accessed via a STE template.
 * 
 * Parameters:
 * 	$section - <Tag> object.
 * 	$lang - The current language.
 * 
 * Returns:
 * 	Array with these fields:
 * 	* id
 * 	* name
 * 	* title
 */
function tag_transform_ste($tag, $lang)
{
	try
	{
		return array(
			"id"    => $tag->get_id(),
			"name"  => $tag->name,
			"title" => $tag->title[$lang]->text,
			"__obj" => $tag
		);
	}
	catch(DoesNotExistError $e)
	{
		return False;
	}
}

/*
 * Function: article_transform_ste
 * Transforms an <Article> object to an array, so it can be accessed via a STE template.
 * 
 * Parameters:
 * 	$article - <Article> object.
 * 	$lang - The current language.
 * 
 * Returns:
 * 	Array with these fields:
 * 	* id
 * 	* urlname
 * 	* fullurl
 * 	* title
 * 	* text
 * 	* excerpt
 * 	* custom (array: name=>value)
 * 	* status (numeric)
 * 	* section (sub-fields: <section_transform_ste>)
 * 	* timestamp
 * 	* tags (array(sub-fields: <tag_transform_ste>))
 * 	* languages (array: language name=>url)
 * 	* comments_allowed
 */
function article_transform_ste($article, $lang)
{
	global $rel_path_to_root;
	
	$languages = array();
	$a_section = $article->get_section();
	foreach($article->title as $language => $_)
		$languages[$language] = "$rel_path_to_root/$language/{$a_section->name}/{$article->urlname}";
	
	return array(
		"id"               => $article->get_id(),
		"urlname"          => $article->urlname,
		"fullurl"          => htmlesc("$rel_path_to_root/$lang/{$a_section->name}/{$article->urlname}"),
		"title"            => htmlesc($article->title[$lang]->text),
		"text"             => textprocessor_apply(str_replace("%root%", $rel_path_to_root, $article->text[$lang]->text), $article->text[$lang]->texttype),
		"excerpt"          => textprocessor_apply(str_replace("%root%", $rel_path_to_root, $article->excerpt[$lang]->text), $article->excerpt[$lang]->texttype),
		"custom"           => $article->custom,
		"status"           => $article->status,
		"section"          => section_transform_ste($a_section, $lang),
		"timestamp"        => $article->timestamp,
		"tags"             => array_filter(array_map(function($tag) use ($lang) { return tag_transform_ste($tag, $lang); }, $article->get_tags())),
		"languages"        => $languages,
		"comments_allowed" => $article->allow_comments,
		"__obj"            => $article
	);
}

/*
 * Function: comment_transform_ste
 * Transforms an <Comment> object to an array, so it can be accessed via a STE template.
 * 
 * Parameters:
 * 	$comment - <Comment> object.
 * 
 * Returns:
 * 	Array with these fields:
 * 	* id
 * 	* text
 * 	* author
 * 	* timestamp
 */
function comment_transform_ste($comment)
{
	global $rel_path_to_root, $ratatoeskr_settings;
	
	return array(
		"id"        => $comment->get_id(),
		"text"      => $comment->create_html(),
		"author"    => htmlesc($comment->author_name),
		"timestamp" => $comment->get_timestamp(),
		"__obj"     => $comment
	);
}

/* Register some tags for the template engine */
/*
 * STETag: articles_get
 * Get articles by custom criterias. Will only get articles, that are available in the current language ($language).
 * The fields of an article can be looked up at <article_transform_ste>.
 * 
 * Parameters:
 * 	var      - (mandatory) The name of the variable, where the current article should be stored at.
 * 	id       - (optional)  Filter by ID.
 * 	urlname  - (optional)  Filter by urlname.
 * 	section  - (optional)  Filter by section (section name).
 * 	status   - (optional)  Filter by status (numeric, <ARTICLE_STATUS_>).
 * 	tag      - (optional)  Filter by tag (tag name).
 * 	sort     - (optional)  How to sort. Format: "fieldname direction" where fieldname is one of [id, urlname, title, timestamp] and direction is one of [asc, desc].
 * 	perpage  - (optional)  How many articles should be shown per page (default unlimited).
 * 	page     - (optional)  On which page are we (starting with 1). Useful in combination with $current[page], <page_prev> and <page_next>. (Default: 1)
 * 	maxpage  - (optional)  (variable name) If given, the number of pages are stored in this variable.
 * 	skip     - (optional)  How many articles should be skipped? (Default: none)
 * 	count    - (optional)  How many articles to output. (Default unlimited)
 *
 * Tag Content:
 * 	The tag's content will be executed for every article. The current article will be written to the variable specified by the var parameter before.
 *
 * Returns:
 * 	All results from the tag content.
 */
$ste->register_tag("articles_get", function($ste, $params, $sub)
{
	$lang = $ste->vars["language"];
	
	if(!isset($params["var"]))
		throw new \ste\RuntimeError("Parameter var is needed in article_get!");
	if(isset($params["section"]))
	{
		try
		{
			$params["section"] = Section::by_name($params["section"]);
		}
		catch(NotFoundError $e)
		{
			unset($params["section"]);
		}
	}
	
	$criterias = array("langavail" => $lang, "onlyvisible" => True);
	if(isset($params["id"]))
		$criterias["id"] = $params["id"];
	if(isset($params["urlname"]))
		$criterias["urlname"];
	if(isset($params["section"]))
		$criterias["section"] = $params["section"];
	if(isset($params["status"]))
		$criterias["status"] = $params["status"];
	if(isset($params["tag"]))
	{
		try
		{
			$criterias["tag"] = Tag::by_name($params["tag"]);
		}
		catch(DoesNotExistError $e) {}
	}
	
	$field     = NULL;
	$direction = NULL;
	if(isset($params["sort"]))
	{
		list($field, $direction) = explode(" ", $params["sort"]);
		if($direction === NULL)
			$field = NULL;
		$direction = strtoupper($direction);
	}
	
	$result = Article::by_multi($criterias, $field, $direction, @$params["count"], @$params["skip"], @$params["perpage"], @$params["page"], $maxpage);
	
	$result = array_map(function($article) use ($lang) { return article_transform_ste($article, $lang); }, $result);
	
	if(isset($params["perpage"]) and isset($params["maxpage"]))
		$ste->set_var_by_name($params["maxpage"], $maxpage == 0 ? 1 : $maxpage);
	
	$output = "";
	foreach($result as $article)
	{
		$ste->set_var_by_name($params["var"], $article);
		$output .= $sub($ste);
	}
	return $output;
});

/*
 * STETag: section_list
 * Iterate over all sections.
 * The fields of a section can be looked up at <section_transform_ste>.
 * 
 * Parameters:
 * 	var             - (mandatory) The name of the variable, where the current section should be stored at.
 * 	exclude         - (optional)  Sections to exclude
 * 	include_default - (optional)  Should the default section be included (default: No).
 * 
 * Tag Content:
 * 	The tag's content will be executed for every section. The current section will be written to the variable specified by the var parameter before.
 *
 * Returns:
 * 	All results from the tag content.
 */
$ste->register_tag("section_list", function($ste, $params, $sub)
{
	global $ratatoeskr_settings;
	$lang = $ste->vars["language"];
	
	$default_section = $ratatoeskr_settings["default_section"];
	
	if(!isset($params["var"]))
		throw new \ste\RuntimeException("Parameter var is needed in article_get!");
	
	$result = Section::all();
	
	if(isset($params["exclude"]))
	{
		$exclude = explode(",", $params["exclude"]);
		$result = array_filter($result, function($section) use ($exclude) { return !in_array($section->name, $exclude); });
	}
	
	$result = array_filter($result, function($section) use ($default_section) { return $section->get_id() != $default_section; });
	
	$result = array_map(function($section) use ($lang) { return section_transform_ste($section, $lang); }, $result);
	
	if($ste->evalbool($params["include_default"]))
	{
		$default = section_transform_ste(Section::by_id($default_section), $lang);
		array_unshift($result, $default);
	}
	
	$output = "";
	foreach($result as $section)
	{
		$ste->set_var_by_name($params["var"], $section);
		$output .= $sub($ste);
	}
	return $output;
});

/*
 * STETag: article_comments_count
 * Get the number of comments for an article.
 * 
 * Parameters:
 * 	article - (mandatory) The name of the variable, where the article is stored at.
 * 
 * Returns:
 * 	The number of comments.
 */
$ste->register_tag("article_comments_count", function($ste, $params, $sub)
{
	if(!isset($params["article"]))
		throw new \ste\RuntimeException("Need parameter 'article' in ste:article_comments_count.");
	$tpl_article = $ste->get_var_reference($params["article"], False);
	$lang = $ste->vars["language"];
	
	if(isset($tpl_article["__obj"]))
		$article = $tpl_article["__obj"];
	else
	{
		try
		{
			$article = Article::by_id(@$tpl_article["id"]);
		}
		catch(DoesNotExistError $e)
		{
			return 0;
		}
	}
	$comments = $article->get_comments($lang, True);
	$tpl_article["__comments"] = $comments;
	return count($comments);
});

/*
 * STETag: article_comments
 * List all comments for an article.
 * The fields of a comment can be looked up at <comment_transform_ste>.
 * 
 * Parameters:
 * 	var     - (mandatory) The name of the variable, where the current comment should be stored at.
 * 	article - (mandatory) The name of the variable, where the article is stored at.
 * 	sort    - (optional)  Should the comments be sorted chronologically (asc) or inverse (desc)? Default: asc
 * 
 * Tag Content:
 * 	The tag's content will be executed for every comment. The current comment will be written to the variable specified by the var parameter before.
 * 
 * Returns:
 * 	All results from the tag content.
 */
$ste->register_tag("article_comments", function($ste, $params, $sub)
{
	if(!isset($params["var"]))
		throw new \ste\RuntimeException("Need parameter 'var' in ste:article_comments.");
	if(!isset($params["article"]))
		throw new \ste\RuntimeException("Need parameter 'article' in ste:article_comments.");
	$tpl_article = $ste->get_var_reference($params["article"], False);
	$lang = $ste->vars["language"];
	
	if(isset($tpl_article["__comments"]))
		$comments = $tpl_article["__comments"];
	else
	{
		if(isset($tpl_article["__obj"]))
			$article = $tpl_article["__obj"];
		else
		{
			try
			{
				$article = Article::by_id(@$tpl_article["id"]);
			}
			catch(DoesNotExistError $e)
			{
				return "";
			}
		}
		
		$comments = $article->get_comments($lang, True);
	}
	
	$sortdir = (@$params["sort"] == "desc") ? -1 : 1;
	usort($comments, function($a,$b) use ($sortdir) { return intcmp($a->get_timestamp(), $b->get_timestamp()) * $sortdir; });
	
	$comments = array_map("comment_transform_ste", $comments);
	
	$output = "";
	foreach($comments as $comment)
	{
		$ste->set_var_by_name($params["var"], $comment);
		$output .= $sub($ste);
	}
	return $output;
});

/*
 * STETag: comment_form
 * Generates a HTML form tag that allows the visitor to write a comment.
 * 
 * Parameters:
 * 	article - (mandatory) The name of the variable, where the article is stored at.
 * 	default - (optional)  If not empty, a default formular with the mandatory fields will be generated.
 * 
 * Tag Content:
 * 	The tag's content will be written into the HTML form tag.
 * 	You have at least to define these fields:
 * 	
 * 	* <input type="text" name="author_name" />    - The Name of the author.
 * 	* <input type="text" name="author_mail" />    - The E-Mailaddress of the author.
 * 	* <textarea name="comment_text"></textarea>   - The Text of the comment.
 * 	* <input type="submit" name="post_comment" /> - Submit button.
 * 	
 * 	You might also want to define this:
 * 	
 * 	* <input type="submit" name="preview_comment" /> - For a preview of the comment.
 * 	
 * 	If the parameter default is not empty, the tag's content will be thrown away.
 * 
 * Returns:
 * 	The finished HTML form.
 * 
 * See Also:
 * 	The "prevcomment" field in <$current>.
 */
$ste->register_tag("comment_form", function($ste, $params, $sub)
{
	global $translation;
	if(!isset($params["article"]))
		throw new \ste\RuntimeException("Need parameter 'article' in ste:comment_form.");
	$tpl_article = $ste->get_var_reference($params["article"], False);
	
	if(isset($tpl_article["__obj"]))
			$article = $tpl_article["__obj"];
	else
	{
		try
		{
			$article = Article::by_id(@$tpl_article["id"]);
		}
		catch(DoesNotExistError $e)
		{
			return "";
		}
	}
	
	if(!$article->allow_comments)
		return "";
	
	/* A token protects from double sending of the same form and is also a simple protection from stupid spambots. */
	$token = uniqid("", True);
	$_SESSION["ratatoeskr_comment_tokens"][$token] = time();
	
	$form_header = "<form action=\"{$tpl_article["fullurl"]}?comment\" method=\"post\" accept-charset=\"UTF-8\"><input type=\"hidden\" name=\"comment_token\" value=\"$token\" />";
	
	if($ste->evalbool(@$params["default"]))
		$form_body = "<p>{$translation["comment_form_name"]}: <input type=\"text\" name=\"author_name\" value=\"" . htmlesc(@$_POST["author_name"]) . "\" /></p>
<p>{$translation["comment_form_mail"]}: <input type=\"text\" name=\"author_mail\" value=\"" . htmlesc(@$_POST["author_mail"]) . "\" /></p>
<p>{$translation["comment_form_text"]}:<br /><textarea name=\"comment_text\" cols=\"50\" rows=\"10\">" . htmlesc(@$_POST["comment_text"]) . "</textarea></p>
<p><input type=\"submit\" name=\"post_comment\" /></p>";
	else
	{
		$ste->vars["current"]["oldcomment"] = array(
			"name" => @$_POST["author_name"],
			"mail" => @$_POST["author_mail"],
			"text" => @$_POST["comment_text"]
		);
		$form_body = $sub($ste);
		unset($ste->vars["current"]["oldcomment"]);
	}
	
	return "$form_header\n$form_body\n</form>";
});

/*
 * STETags: Page control
 * These tags can create links to the previous/next page.
 * 
 * page_prev - Link to the previous page (if available).
 * page_next - Link to the next page (if available).
 * 
 * Parameters:
 * 	current - (mandatory) The current page number.
 * 	maxpage - (mandatory) How many pages in total?
 * 	default - (optional)  If not empty, a default localized link text will be used.
 * 
 * Tag Content:
 * 	The tag's content will be used as the link text.
 * 
 * Returns:
 * 	A Link to the previous / next page.
 */

$ste->register_tag("page_prev", function($ste, $params, $sub)
{
	global $translation;
	if(!isset($params["current"]))
		throw new \ste\RuntimeException("Need parameter 'current' in ste:page_prev.");
	if(!isset($params["maxpage"]))
		throw new \ste\RuntimeException("Need parameter 'maxpage' in ste:page_prev.");
	
	if($params["current"] == 1)
		return "";
	
	parse_str(parse_url($_SERVER["REQUEST_URI"], PHP_URL_QUERY), $query);
	$query["page"] = $params["current"] - 1;
	$url = $_SERVER["REDIRECT_URL"] . "?" . http_build_query($query);
	return "<a href=\"" . htmlesc($url) . "\">" . (($ste->evalbool(@$params["default"])) ? $translation["page_prev"] : $sub($ste)) . "</a>";
});

$ste->register_tag("page_next", function($ste, $params, $sub)
{
	global $translation;
	if(!isset($params["current"]))
		throw new \ste\RuntimeException("Need parameter 'current' in ste:page_next.");
	if(!isset($params["maxpage"]))
		throw new \ste\RuntimeException("Need parameter 'maxpage' in ste:page_next.");
	
	if($params["current"] == $params["maxpage"])
		return "";
	
	parse_str(parse_url($_SERVER["REQUEST_URI"], PHP_URL_QUERY), $query);
	$query["page"] = $params["current"] + 1;
	$url = $_SERVER["REDIRECT_URL"] . "?" . http_build_query($query);
	return "<a href=\"" . htmlesc($url) . "\">" . (($ste->evalbool(@$params["default"])) ? $translation["page_next"] : $sub($ste)) . "</a>";
});

/*
 * STETag: languages
 * List all languages available in the current context.
 * 
 * Parameters:
 * 	var - (mandatory) The name of the variable, where the current language information should be stored at.
 * 
 * Sub-fields of var:
 * 	short    - 2 letter code of language
 * 	fullname - The full name of the language
 * 	url      - URL to the current page in this language
 *
 * Tag Content:
 * 	The tag's content will be executed for every language. The current language will be written to the variable specified by the var parameter before.
 * 
 * Returns:
 * 	All results from the tag content.
 */
$ste->register_tag("languages", function($ste, $params, $sub)
{
	global $languages, $ratatoeskr_settings, $rel_path_to_root;
	
	if(!isset($params["var"]))
		throw new Exception("Need parameter 'var' in ste:languages.");
	
	$langs = array();
	if(isset($ste->vars["current"]["article"]))
	{
		if(isset($ste->vars["current"]["article"]["__obj"]))
			$article = $ste->vars["current"]["article"]["__obj"];
		else
		{
			try
			{
				$article = Article::by_id($ste->vars["current"]["article"]["id"]);
			}
			catch(DoesNotExistError $e) {}
		}
		foreach($article->title as $lang => $_)
			$langs[] = $lang;
	}
	else
	{
		
		foreach($ratatoeskr_settings["languages"] as $lang)
			$langs[] = $lang;
	}
	
	$output = "";
	foreach($langs as $lang)
	{
		$ste->set_var_by_name($params["var"], array(
			"short"    => $lang,
			"fullname" => htmlesc($languages[$lang]["language"]),
			"url"      => htmlesc("$rel_path_to_root/$lang/" . implode("/", array_slice($ste->vars["current"]["url_fragments"], 1)))
		));
		$output .= $sub($ste);
	}
	return $output;
});

/*
 * STETag: styles_load
 * Load all current styles.
 * 
 * Parameters:
 * 	mode - (optional) Either "embed" or "link". Default: link
 * 
 * Returns:
 * 	The current styles (either linked or embedded)
 */
$ste->register_tag("styles_load", function($ste, $params, $sub)
{
	global $rel_path_to_root;
	if(isset($params["mode"]) and (($params["mode"] == "embed") or ($params["mode"] == "link")))
		$mode = $params["mode"];
	else
		$mode = "link";
	
	if($mode == "embed")
	{
		$output = "";
		if(isset($ste->vars["current"]["__style_objs"]))
		{
			foreach($ste->vars["current"]["__style_objs"] as $style)
				$output .= "/* Style: {$style->name} */\n" . $style->code . "\n";
		}
		else
		{
			foreach($ste->vars["current"]["styles"] as $stylename)
			{
				try
				{
					$style = Style::by_name($stylename);
					$output .= "/* Style: $stylename */\n" . str_replace("%root%", $rel_path_to_root, $style->code) . "\n";
				}
				catch(DoesNotExistError $e)
				{
					$output .= "/* Warning: Failed to load style: $stylename */\n";
				}
			}
		}
		$output = "<style type=\"text/css\">\n" . htmlesc($output) . "</style>";
	}
	else
	{
		$output = "";
		foreach($ste->vars["current"]["styles"] as $stylename)
			$output .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"$rel_path_to_root/css.php?name=" . htmlesc($stylename) . "\" />\n";
	}
	return $output;
});

/*
 * STETag: title
 * Generate a HTML title tag for your site.
 * 
 * Tag Content:
 * 	The name of your site.
 * 
 * Returns:
 * 	A HTML title tag that describes the current (sub)page.
 */
$ste->register_tag("title", function($ste, $params, $sub)
{
	$pagetitle = $sub($ste);
	if(isset($ste->vars["current"]["article"]))
		return "<title>" . htmlesc($ste->vars["current"]["article"]["title"]) . " – $pagetitle" . "</title>";
	if(isset($ste->vars["current"]["section"]))
		return "<title>" . htmlesc($ste->vars["current"]["section"]["title"]) . " – $pagetitle" . "</title>";
	return "<title>$pagetitle</title>";
});

/*
 * STEVar: $current
 * Holds information about the current page in the frontend (the part of the webpage, the visitor sees).
 * 
 * $current has these fields:
 * 	* article       - Only set if a single article is shown. Holds information about an article. (sub-fields are described at <article_transform_ste>).
 * 	* section       - Only set if a whole section is shown. Holds information about an section. (sub-fields are described at <section_transform_ste>).
 * 	* tag           - Only set if all articles with the same tag should be shown (pseudo section _tags). Holds information about a tag. (sub-fields are described at <tag_transform_ste>).
 * 	* page          - Which subpage is shown? Useful with <page_prev>, <page_next> and the page parameter of <articles_get>. Default: 1
 * 	* commented     - True, if the visitor has successfully written a comment.
 * 	* comment_fail  - If the user tried to comment, but the system rejected the comment, this will be set and will contain the error message.
 * 	* comment_prev  - If the user wanted to preview the article, this will be set and contain the HTML code of the comment.
 * 	* styles        - The styles, that should be loaded. You can also just use <styles_load>.
 * 	* url_fragments - Array of URL parts. Mainly used internally, so you *really* should not modify this one...
 * 	* oldcomment    - The data of the previously sent formular (subfields: name, mail, text). Only set inside the <comment_form> tag.
 * 	
 * 	Plugins might insert their own $current fields.
 */

/*
 * STEVar: $language
 * The short form (e.g. "en" for English, "de" for German, ...) of the current language.
 */

$comment_validators = array(
	function() /* Basic validator. Checks, if all mandatory fields are set and if the form token is okay. Also provides some stupid spambot protection...  */
	{
		global $translation;
		if(empty($_POST["author_name"]))
			throw new CommentRejected($translation["author_name_missing"]);
		if(empty($_POST["author_mail"]))
			throw new CommentRejected($translation["author_email_missing"]);
		if(empty($_POST["comment_text"]))
			throw new CommentRejected($translation["comment_text_missing"]);
		if(!isset($_POST["comment_token"]))
			throw new CommentRejected($translation["comment_form_invalid"]);
		if(!isset($_SESSION["ratatoeskr_comment_tokens"][$_POST["comment_token"]]))
			throw new CommentRejected($translation["comment_form_invalid"]);
		if(time() - $_SESSION["ratatoeskr_comment_tokens"][$_POST["comment_token"]] < 10) /* Comment filled in in under 10 seconds? Most likely a spambot. */
			throw new CommentRejected($translation["comment_too_fast"]);
		unset($_SESSION["ratatoeskr_comment_tokens"][$_POST["comment_token"]]);
	}
);

$on_comment_store = array();

/*
 * Function: frontend_url_handler
 */
function frontend_url_handler(&$data, $url_now, &$url_next)
{
	global $ste, $ratatoeskr_settings, $languages, $metasections, $comment_validators, $on_comment_store;
	$path = array_merge(array($url_now), $url_next);
	$url_next = array();
	
	/* If no language or an invalid language was given, fix it. */
	if((count($path) == 0) or (!isset($languages[$path[0]])))
	{
		if(count($path > 0))
			array_shift($path);
		array_unshift($path, $ratatoeskr_settings["default_language"]);
	}
	
	$ste->vars["current"]["url_fragments"] = $path;
	
	$lang = array_shift($path);
	$ste->vars["language"] = $lang;
	load_language($languages[$lang]["translation_exist"] ? $lang : "en"); /* English is always available */
	
	if(count($path) != 0)
		$section_name = array_shift($path);
	else
		$section_name = NULL;
	
	if($section_name == "_tags")
	{
		try
		{
			$tag = Tag::by_name(array_shift($path));
		}
		catch(DoesNotExistError $e)
		{
			throw new NotFoundError();
		}
		$ste->vars["current"]["tag"] = tag_transform_ste($tag, $lang);
	}
	else
	{
		if($section_name === NULL)
			$section = Section::by_id($ratatoeskr_settings["default_section"]);
		else
		{
			try
			{
				$section = Section::by_name($section_name);
			}
			catch(DoesNotExistError $e)
			{
				throw new NotFoundError();
			}
		}
		
		if(count($path)== 0)
			$ste->vars["current"]["section"] = section_transform_ste($section, $lang);
		else
		{
			try
			{
				$article = Article::by_urlname(array_shift($path));
			}
			catch(DoesNotExistError $e)
			{
				throw new NotFoundError();
			}
			$ste->vars["current"]["article"] = article_transform_ste($article, $lang);
			
			if(isset($_GET["comment"]))
			{
				if(isset($_POST["preview_comment"]))
					$ste->vars["current"]["comment_prev"] = Comment::htmlize_comment_text($_POST["comment_text"]);
				else if(isset($_POST["post_comment"]))
				{
					$rejected = False;
					try
					{
						foreach($comment_validators as $validator)
							call_user_func($validator);
					}
					catch(CommentRejected $e)
					{
						$ste->vars["current"]["comment_fail"] = htmlesc($e->getMessage());
						$rejected = True;
					}
					if(!$rejected)
					{
						$comment = Comment::create($article, $lang);
						$comment->author_name = $_POST["author_name"];
						$comment->author_mail = $_POST["author_mail"];
						$comment->text        = $_POST["comment_text"];
						$comment->save();
						$ste->vars["current"]["commented"] = "Yes";
						foreach($on_comment_store as $ocs_fx)
							call_user_func($ocs_fx, $comment);
					}
				}
			}
		}
	}
	
	$ste->vars["current"]["page"] = (isset($_GET["page"]) and is_numeric($_GET["page"])) ? $_GET["page"] : 1;
	
	if(!isset($section))
		$section = Section::by_id($ratatoeskr_settings["default_section"]);
	
	foreach($section->get_styles() as $style)
	{
		$ste->vars["current"]["styles"][] = $style->name;
		$ste->vars["current"]["__style_objs"][] = $style;
	}
	echo $ste->exectemplate("/usertemplates/" . $section->template);
}

/*
 * Class: CommentRejected
 * An Exeption a comment validator can throw, if the validation failed.
 * 
 * See Also:
 * 	<register_comment_validator>
 */
class CommentRejected extends Exception {}

?>
