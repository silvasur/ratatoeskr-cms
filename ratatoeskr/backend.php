<?php
/*
 * File: ratatoeskr/backend.php
 * The backend.
 *
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

require_once(dirname(__FILE__) . "/sys/models.php");
require_once(dirname(__FILE__) . "/sys/pwhash.php");
require_once(dirname(__FILE__) . "/sys/textprocessors.php");
require_once(dirname(__FILE__) . "/sys/plugin_api.php");
require_once(dirname(__FILE__) . "/languages.php");

$admin_grp = null;

/* Mass creation of tags. */
function maketags($tagnames, $lang)
{
    $rv = [];
    foreach ($tagnames as $tagname) {
        if (empty($tagname)) {
            continue;
        }
        try {
            $tag = Tag::by_name($tagname);
        } catch (DoesNotExistError $e) {
            $tag = Tag::create($tagname);
            $tag->title[$lang] = new Translation($tagname, "");
        }
        $rv[] = $tag;
    }
    return $rv;
}

$backend_subactions = null;

function build_backend_subactions()
{
    global $backend_subactions, $pluginpages_handlers;

    $backend_subactions = url_action_subactions([
    "_index" => url_action_alias(["login"]),
    "index" => url_action_alias(["login"]),
    /* _prelude guarantees that the user is logged in properly, so we do not have to care about that later, and sets some STE vars. */
    "_prelude" => function (&$data, $url_now, &$url_next) {
        global $ratatoeskr_settings, $admin_grp, $ste, $languages;

        if ($admin_grp === null) {
            $admin_grp = Group::by_name("admins");
        }

        $ste->vars["all_languages"] = [];
        $ste->vars["all_langcodes"] = [];
        foreach ($languages as $code => $data) {
            $ste->vars["all_languages"][$code] = $data["language"];
            $ste->vars["all_langcodes"][]      = $code;
        }
        ksort($ste->vars["all_languages"]);
        sort($ste->vars["all_langcodes"]);


        /* Check authentification */
        if (isset($_SESSION["ratatoeskr_uid"])) {
            try {
                $user = User::by_id($_SESSION["ratatoeskr_uid"]);
                if (($user->pwhash == $_SESSION["ratatoeskr_pwhash"]) and $user->member_of($admin_grp)) {
                    if (empty($user->language)) {
                        $user->language = $ratatoeskr_settings["default_language"];
                        $user->save();
                    }
                    load_language($user->language);

                    if ($url_next[0] == "login") {
                        $url_next = ["content", "write"];
                    }
                    $data["user"] = $user;
                    $ste->vars["user"] = ["id" => $user->get_id(), "name" => $user->username, "lang" => $user->language];

                    return; /* Authentification successful, continue  */
                } else {
                    unset($_SESSION["ratatoeskr_uid"]);
                }
            } catch (DoesNotExistError $e) {
                unset($_SESSION["ratatoeskr_uid"]);
            }
        }
        load_language();

        /* If we are here, user is not logged in... */
        $url_next = ["login"];
    },
    "login" => url_action_simple(function ($data) {
        /**
         * @var \ste\STECore $ste
         * @var Group|null $admin_grp
         */
        global $ste, $admin_grp;
        if (!empty($_POST["user"])) {
            try {
                $user = User::by_name($_POST["user"]);
                if (!PasswordHash::validate($_POST["password"], $user->pwhash)) {
                    throw new Exception();
                }
                if (!$user->member_of($admin_grp)) {
                    throw new Exception();
                }

                /* Login successful. */
                $_SESSION["ratatoeskr_uid"]    = $user->get_id();
                $_SESSION["ratatoeskr_pwhash"] = $user->pwhash;
                load_language($user->language);
                $data["user"] = $user;
                $ste->vars["user"] = ["id" => $user->get_id(), "name" => $user->username, "lang" => $user->language];
            } catch (Exception $e) {
                $ste->vars["login_failed"] = true;
            }

            if (isset($data["user"])) {
                throw new Redirect(["content", "write"]);
            }
        }

        echo $ste->exectemplate("/systemtemplates/backend_login.html");
    }),
    "logout" => url_action_simple(function ($data) {
        unset($_SESSION["ratatoeskr_uid"]);
        unset($_SESSION["ratatoeskr_pwhash"]);
        load_language();
        throw new Redirect(["login"]);
    }),
    "content" => url_action_subactions([
        "write" => function (&$data, $url_now, &$url_next) {
            /**
             * @var \ste\STECore $ste
             * @var array $translation
             * @var array $textprocessors
             * @var Settings $ratatoeskr_settings
             * @var array $languages
             * @var array $articleeditor_plugins
             */
            global $ste, $translation, $textprocessors, $ratatoeskr_settings, $languages, $articleeditor_plugins;

            list($article, $editlang) = array_slice($url_next, 0);
            if (!isset($editlang)) {
                $editlang = $data["user"]->language;
            }
            if (isset($article)) {
                $ste->vars["article_editurl"] = urlencode($article) . "/" . urlencode($editlang);
            } else {
                $ste->vars["article_editurl"] = "";
            }

            $url_next = [];

            $default_section = Section::by_id($ratatoeskr_settings["default_section"]);

            $ste->vars["section"] = "content";
            $ste->vars["submenu"] = isset($article) ? "articles" : "newarticle";

            $ste->vars["textprocessors"] = [];
            foreach ($textprocessors as $txtproc => $properties) {
                if ($properties[1]) {
                    $ste->vars["textprocessors"][] = $txtproc;
                }
            }

            $ste->vars["sections"] = [];
            foreach (Section::all() as $section) {
                $ste->vars["sections"][] = $section->name;
            }
            $ste->vars["article_section"] = $default_section->name;

            /* Check Form */
            $fail_reasons = [];

            if (isset($_POST["save_article"])) {
                if (!Article::test_urlname($_POST["urlname"])) {
                    $fail_reasons[] = $translation["invalid_urlname"];
                } else {
                    $inputs["urlname"] = $_POST["urlname"];
                }
                if (!Article::test_status(@$_POST["article_status"])) {
                    $fail_reasons[] = $translation["invalid_article_status"];
                } else {
                    $inputs["article_status"] = (int) $_POST["article_status"];
                }
                if (!isset($textprocessors[@$_POST["content_txtproc"]])) {
                    $fail_reasons[] = $translation["unknown_txtproc"];
                } else {
                    $inputs["content_txtproc"] = $_POST["content_txtproc"];
                }
                if (!isset($textprocessors[@$_POST["excerpt_txtproc"]])) {
                    $fail_reasons[] = $translation["unknown_txtproc"];
                } else {
                    $inputs["excerpt_txtproc"] = $_POST["excerpt_txtproc"];
                }
                if (!empty($_POST["date"])) {
                    if (($time_tmp = @DateTime::createFromFormat("Y-m-d H:i:s", @$_POST["date"])) === false) {
                        $fail_reasons[] = $translation["invalid_date"];
                    } else {
                        $inputs["date"] = @$time_tmp->getTimestamp();
                    }
                } else {
                    $inputs["date"] = time();
                }
                $inputs["allow_comments"] = !(empty($_POST["allow_comments"]) or ($_POST["allow_comments"] != "yes"));

                try {
                    $inputs["article_section"] = Section::by_name($_POST["section"]);
                } catch (DoesNotExistError $e) {
                    $fail_reasons[] = $translation["unknown_section"];
                }

                $inputs["title"]      = $_POST["title"];
                $inputs["content"]    = $_POST["content"];
                $inputs["excerpt"]    = $_POST["excerpt"];
                $inputs["tags"]       = array_filter(array_map("trim", explode(",", $_POST["tags"])), function ($t) {
                    return !empty($t);
                });
                if (isset($_POST["saveaslang"])) {
                    $editlang = $_POST["saveaslang"];
                }
            } else {
                /* Call articleeditor plugins */
                $article = empty($article) ? null : Article::by_urlname($article);
                foreach ($articleeditor_plugins as $plugin) {
                    call_user_func($plugin["fx"], $article, false);
                }
            }

            $fill_article = function (Article &$article, array $inputs, string $editlang) {
                $article->urlname   = $inputs["urlname"];
                $article->status    = $inputs["article_status"];
                $article->timestamp = $inputs["date"];
                $article->title[$editlang]   = new Translation($inputs["title"], "");
                $article->text[$editlang]    = new Translation($inputs["content"], $inputs["content_txtproc"]);
                $article->excerpt[$editlang] = new Translation($inputs["excerpt"], $inputs["excerpt_txtproc"]);
                $article->set_tags(maketags($inputs["tags"], $editlang));
                $article->set_section($inputs["article_section"]);
                $article->allow_comments = $inputs["allow_comments"];
            };

            if (empty($article)) {
                /* New Article */
                $ste->vars["pagetitle"] = $translation["new_article"];
                if (empty($fail_reasons) and isset($_POST["save_article"])) {
                    $article = Article::create($inputs["urlname"]);
                    $fill_article($article, $inputs, $editlang);

                    /* Calling articleeditor plugins */
                    $call_after_save = [];
                    foreach ($articleeditor_plugins as $plugin) {
                        $result = call_user_func($plugin["fx"], $article, true);
                        if (is_string($result)) {
                            $fail_reasons[] = $result;
                        } elseif ($result !== null) {
                            $call_after_save[] = $result;
                        }
                    }

                    if (empty($fail_reasons)) {
                        try {
                            $article->save();
                            foreach ($call_after_save as $cb) {
                                call_user_func($cb, $article);
                            }
                            $ste->vars["article_editurl"] = urlencode($article->urlname) . "/" . urlencode($editlang);
                            $ste->vars["success"] = htmlesc($translation["article_save_success"]);
                        } catch (AlreadyExistsError $e) {
                            $fail_reasons[] = $translation["article_name_already_in_use"];
                        }
                    }
                }
            } else {
                try {
                    if (!($article instanceof Article)) {
                        $article = Article::by_urlname($article);
                    }
                } catch (DoesNotExistError $e) {
                    throw new NotFoundError();
                }

                if (empty($fail_reasons) and isset($_POST["save_article"])) {
                    $fill_article($article, $inputs, $editlang);

                    /* Calling articleeditor plugins */
                    $call_after_save = [];
                    foreach ($articleeditor_plugins as $plugin) {
                        $result = call_user_func($plugin["fx"], $article, true);
                        if (is_string($result)) {
                            $fail_reasons[] = $result;
                        } elseif ($result !== null) {
                            $call_after_save[] = $result;
                        }
                    }

                    if (empty($fail_reasons)) {
                        try {
                            $article->save();
                            foreach ($call_after_save as $cb) {
                                call_user_func($cb, $article);
                            }
                            $ste->vars["article_editurl"] = urlencode($article->urlname) . "/" . urlencode($editlang);
                            $ste->vars["success"] = htmlesc($translation["article_save_success"]);
                        } catch (AlreadyExistsError $e) {
                            $fail_reasons[] = $translation["article_name_already_in_use"];
                        }
                    }
                }

                if (!isset($article->title[$editlang])) {
                    $langs_available = [];
                    foreach ($article->title as $lang => $_) {
                        $langs_available[] = $lang;
                    }
                    $editlang = $langs_available[0];
                }

                foreach ([
                    "urlname"        => "urlname",
                    "status"         => "article_status",
                    "timestamp"      => "date",
                    "allow_comments" => "allow_comments"
                ] as $prop => $k_out) {
                    if (!isset($inputs[$k_out])) {
                        $inputs[$k_out] = $article->$prop;
                    }
                }
                if (!isset($inputs["title"])) {
                    $inputs["title"] = $article->title[$editlang]->text;
                }
                if (!isset($inputs["content"])) {
                    $translation_obj           = $article->text[$editlang];
                    $inputs["content"]         = $translation_obj->text;
                    $inputs["content_txtproc"] = $translation_obj->texttype;
                }
                if (!isset($inputs["excerpt"])) {
                    $translation_obj           = $article->excerpt[$editlang];
                    $inputs["excerpt"]         = $translation_obj->text;
                    $inputs["excerpt_txtproc"] = $translation_obj->texttype;
                }
                if (!isset($inputs["article_section"])) {
                    $inputs["article_section"] = $article->get_section();
                }
                if (!isset($inputs["tags"])) {
                    $inputs["tags"] = array_map(function ($tag) use ($editlang) {
                        return $tag->name;
                    }, $article->get_tags());
                }
                $ste->vars["morelangs"] = [];
                $ste->vars["pagetitle"] = $article->title[$editlang]->text;
                foreach ($article->title as $lang => $_) {
                    if ($lang != $editlang) {
                        $ste->vars["morelangs"][] = ["url" => urlencode($article->urlname) . "/$lang", "full" => "($lang) " . $languages[$lang]["language"]];
                    }
                }
            }

            if (!isset($inputs["date"])) {
                $inputs["date"] = time();
            }
            if (!isset($inputs["article_status"])) {
                $inputs["article_status"] = ARTICLE_STATUS_LIVE;
            }

            /* Push data back to template */
            if (isset($inputs["tags"])) {
                $ste->vars["tags"] = implode(", ", $inputs["tags"]);
            }
            if (isset($inputs["article_section"])) {
                $ste->vars["article_section"] = $inputs["article_section"]->name;
            }
            $ste->vars["editlang"] = $editlang;
            foreach ([
                "urlname"         => "urlname",
                "article_status"  => "article_status",
                "title"           => "title",
                "content_txtproc" => "content_txtproc",
                "content"         => "content",
                "excerpt_txtproc" => "excerpt_txtproc",
                "excerpt"         => "excerpt",
                "date"            => "date",
                "allow_comments"  => "allow_comments"
            ] as $k_in => $k_out) {
                if (isset($inputs[$k_in])) {
                    $ste->vars[$k_out] = $inputs[$k_in];
                }
            }

            /* Displaying article editor plugins */
            $ste->vars["displayed_plugins"] = array_map(function ($x) {
                return ["label" => $x["label"], "template" => $x["template"]];
            }, array_filter($articleeditor_plugins, function ($x) {
                return $x["display"];
            }));

            if (!empty($fail_reasons)) {
                $ste->vars["failed"] = $fail_reasons;
            }

            echo $ste->exectemplate("/systemtemplates/content_write.html");
        },
        "tags" => function (&$data, $url_now, &$url_next) {
            global $translation, $languages, $ste;

            $url_next = [];

            $ste->vars["section"]   = "content";
            $ste->vars["submenu"]   = "tags";
            $ste->vars["pagetitle"] = $translation["tags_overview"];

            if (isset($_POST["delete"]) and ($_POST["really_delete"] == "yes")) {
                foreach ($_POST["tag_multiselect"] as $tagid) {
                    try {
                        $tag = Tag::by_id($tagid);
                        $tag->delete();
                    } catch (DoesNotExistError $e) {
                        continue;
                    }
                }

                $ste->vars["success"] = $translation["tags_successfully_deleted"];
            }

            if (isset($_POST["save_changes"])) {
                $newlang = (!empty($_POST["new_language"])) ? $_POST["new_language"] : null;
                $newtag  = null;

                if (!empty($_POST["newtagname"])) {
                    if (!Tag::test_name(@$_POST["newtagname"])) {
                        $ste->vars["error"] = $translation["invalid_tag_name"];
                    } else {
                        $newtag = $_POST["newtagname"];
                    }
                }

                if (($newlang !== null) and (!isset($languages[$newlang]))) {
                    $newlang = null;
                }
                if ($newtag !== null) {
                    try {
                        $newtag = Tag::create($newtag);
                    } catch (AlreadyExistsError $e) {
                        $newtag = null;
                    }
                }

                $translations = [];
                foreach ($_POST as $k => $v) {
                    if (preg_match('/tagtrans_(NEW|[a-z]{2})_(.*)/', $k, $matches) == 1) {
                        $lang = ($matches[1] == "NEW") ? $newlang : $matches[1];
                        $tag  = $matches[2];
                        if ($lang === null) {
                            continue;
                        }
                        $translations[$tag][$lang] = $v;
                    }
                }

                foreach ($translations as $tag => $langmap) {
                    if ($tag == "NEW") {
                        if ($newtag === null) {
                            continue;
                        }
                        $tag = $newtag;
                    } else {
                        try {
                            $tag = Tag::by_id($tag);
                        } catch (DoesNotExistError $e) {
                            continue;
                        }
                    }

                    foreach ($langmap as $l => $text) {
                        if (empty($text)) {
                            unset($tag->title[$l]);
                        } else {
                            $tag->title[$l] = new Translation($text, "");
                        }
                    }

                    $tag->save();
                }

                $ste->vars["success"] = $translation["tags_successfully_edited"];
            }

            $ste->vars["alltags"] = [];
            $taglangs = [];

            $alltags = Tag::all();
            foreach ($alltags as $tag) {
                $transls = [];
                foreach ($tag->title as $l => $t) {
                    if (!in_array($l, $taglangs)) {
                        $taglangs[] = $l;
                    }
                    $transls[$l] = $t->text;
                }

                $ste->vars["alltags"][] = [
                    "id" => $tag->get_id(),
                    "name" => $tag->name,
                    "translations" => $transls
                ];
            }

            $unused_langs = array_diff(array_keys($languages), $taglangs);

            $ste->vars["all_tag_langs"] = [];
            foreach ($taglangs as $l) {
                $ste->vars["all_tag_langs"][$l] = $languages[$l]["language"];
            }
            $ste->vars["unused_languages"] = [];
            foreach ($unused_langs as $l) {
                $ste->vars["unused_languages"][$l] = $languages[$l]["language"];
            }

            echo $ste->exectemplate("/systemtemplates/tags_overview.html");
        },
        "articles" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation;

            $url_next = [];

            $ste->vars["section"]   = "content";
            $ste->vars["submenu"]   = "articles";
            $ste->vars["pagetitle"] = $translation["menu_articles"];

            if (isset($_POST["delete"]) and ($_POST["really_delete"] == "yes")) {
                foreach ($_POST["article_multiselect"] as $article_urlname) {
                    try {
                        $article = Article::by_urlname($article_urlname);
                        $article->delete();
                    } catch (DoesNotExistError $e) {
                        continue;
                    }
                }

                $ste->vars["success"] = $translation["articles_deleted"];
            }

            $articles = Article::all();

            /* Filtering */
            $filterquery = [];
            if (!empty($_GET["filter_urlname"])) {
                $searchfor = strtolower($_GET["filter_urlname"]);
                $articles = array_filter($articles, function ($a) use ($searchfor) {
                    return strpos(strtolower($a->urlname), $searchfor) !== false;
                });
                $filterquery[] = "filter_urlname=" . urlencode($_GET["filter_urlname"]);
                $ste->vars["filter_urlname"] = $_GET["filter_urlname"];
            }
            if (!empty($_GET["filter_tag"])) {
                $searchfor = $_GET["filter_tag"];
                $articles = array_filter($articles, function ($a) use ($searchfor) {
                    foreach ($a->get_tags() as $t) {
                        if ($t->name==$searchfor) {
                            return true;
                        }
                    }
                    return false;
                });
                $filterquery[] = "filter_tag=" . urlencode($searchfor);
                $ste->vars["filter_tag"] = $searchfor;
            }
            if (!empty($_GET["filter_section"])) {
                $searchfor = $_GET["filter_section"];
                $articles = array_filter($articles, function ($a) use ($searchfor) {
                    return $a->get_section()->name == $searchfor;
                });
                $filterquery[] = "filter_section=" . urlencode($searchfor);
                $ste->vars["filter_section"] = $searchfor;
            }
            $ste->vars["filterquery"] = implode("&", $filterquery);

            /* Sorting */
            if (isset($_GET["sort_asc"])) {
                switch ($_GET["sort_asc"]) {
                    case "date":
                        $ste->vars["sortquery"] = "sort_asc=date";
                        $ste->vars["sort_asc_date"] = true;
                        $ste->vars["sorting"] = ["dir" => "asc", "by" => "date"];
                        usort($articles, function ($a, $b) {
                            return intcmp($a->timestamp, $b->timestamp);
                        });
                        break;
                    case "section":
                        $ste->vars["sortquery"] = "sort_asc=section";
                        $ste->vars["sort_asc_section"] = true;
                        $ste->vars["sorting"] = ["dir" => "asc", "by" => "section"];
                        usort($articles, function ($a, $b) {
                            return strcmp($a->get_section()->name, $b->get_section()->name);
                        });
                        break;
                    case "urlname":
                        $ste->vars["sortquery"] = "sort_asc=urlname";
                        // no break
                    default:
                        $ste->vars["sort_asc_urlname"] = true;
                        $ste->vars["sorting"] = ["dir" => "asc", "by" => "urlname"];
                        usort($articles, function ($a, $b) {
                            return strcmp($a->urlname, $b->urlname);
                        });
                        break;
                }
            } elseif (isset($_GET["sort_desc"])) {
                switch ($_GET["sort_desc"]) {
                    case "date":
                        $ste->vars["sortquery"] = "sort_desc=date";
                        $ste->vars["sort_desc_date"] = true;
                        $ste->vars["sorting"] = ["dir" => "desc", "by" => "date"];
                        usort($articles, function ($a, $b) {
                            return intcmp($b->timestamp, $a->timestamp);
                        });
                        break;
                    case "section":
                        $ste->vars["sortquery"] = "sort_desc=section";
                        $ste->vars["sort_desc_section"] = true;
                        $ste->vars["sorting"] = ["dir" => "desc", "by" => "section"];
                        usort($articles, function ($a, $b) {
                            return strcmp($b->get_section()->name, $a->get_section()->name);
                        });
                        break;
                    case "urlname":
                        $ste->vars["sortquery"] = "sort_desc=urlname";
                        $ste->vars["sort_desc_urlname"] = true;
                        $ste->vars["sorting"] = ["dir" => "desc", "by" => "urlname"];
                        usort($articles, function ($a, $b) {
                            return strcmp($b->urlname, $a->urlname);
                        });
                        break;
                    default:
                        $ste->vars["sort_asc_urlname"] = true;
                        $ste->vars["sorting"] = ["dir" => "asc", "by" => "urlname"];
                        usort($articles, function ($a, $b) {
                            return strcmp($a->urlname, $b->urlname);
                        });
                        break;
                }
            } else {
                $ste->vars["sort_asc_urlname"] = true;
                $ste->vars["sorting"] = ["dir" => "asc", "by" => "urlname"];
                usort($articles, function ($a, $b) {
                    return strcmp($a->urlname, $b->urlname);
                });
            }

            $ste->vars["articles"] = array_map(function ($article) {
                $avail_langs = [];
                foreach ($article->title as $lang => $_) {
                    $avail_langs[] = $lang;
                }
                sort($avail_langs);
                $a_section = $article->get_section();
                return [
                    "urlname"   => $article->urlname,
                    "languages" => $avail_langs,
                    "date"      => $article->timestamp,
                    "tags"      => array_map(function ($tag) {
                        return $tag->name;
                    }, $article->get_tags()),
                    "section"   => ["id" => $a_section->get_id(), "name" => $a_section->name]
                ];
            }, $articles);

            echo $ste->exectemplate("/systemtemplates/articles.html");
        },
        "images" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation;

            list($imgid, $imageaction) = $url_next;

            $url_next = [];

            $ste->vars["section"]   = "content";
            $ste->vars["submenu"]   = "images";
            $ste->vars["pagetitle"] = $translation["menu_images"];

            if (!empty($imgid) and is_numeric($imgid)) {
                try {
                    $image = Image::by_id($imgid);
                } catch (DoesNotExistError $e) {
                    throw new NotFoundError();
                }

                if (empty($imageaction)) {
                    throw new NotFoundError();
                } else {
                    if (($imageaction == "markdown") or ($imageaction == "html") or ($imageaction == "ste")) {
                        $ste->vars["pagetitle"]      = $translation["generate_embed_code"];
                        $ste->vars["image_id"]       = $image->get_id();
                        $ste->vars["markup_variant"] = $imageaction;
                        if (isset($_POST["img_alt"])) {
                            if ($imageaction == "markdown") {
                                $ste->vars["embed_code"] = "![" . str_replace("]", "\\]", $_POST["img_alt"]) . "](%root%/images/" . str_replace(")", "\\)", urlencode($image->get_filename())) . ")";
                            } elseif ($imageaction == "html") {
                                $ste->vars["embed_code"] = "<img src=\"%root%/images/" . htmlesc(urlencode($image->get_filename())) . "\" alt=\"" . htmlesc($_POST["img_alt"]) . "\" />";
                            } elseif ($imageaction == "ste") {
                                $ste->vars["embed_code"] = "<img src=\"\$rel_path_to_root/images/" . htmlesc(urlencode($image->get_filename())) . "\" alt=\"" . htmlesc($_POST["img_alt"]) . "\" />";
                            }
                        }

                        echo $ste->exectemplate("/systemtemplates/image_embed.html");
                    } else {
                        throw new NotFoundError();
                    }
                }
                return;
            }

            /* Upload Form */
            if (isset($_POST["upload"])) {
                try {
                    $image = Image::create((!empty($_POST["upload_name"])) ? $_POST["upload_name"] : $_FILES["upload_img"]["name"], $_FILES["upload_img"]["tmp_name"]);
                    $image->save();
                    $ste->vars["success"] = $translation["upload_success"];
                } catch (IOError $e) {
                    $ste->vars["error"] = $translation["upload_failed"];
                } catch (UnknownFileFormat $e) {
                    $ste->vars["error"] = $translation["unknown_file_format"];
                }
            }

            /* Mass delete */
            if (isset($_POST["delete"]) and ($_POST["really_delete"] == "yes")) {
                foreach ($_POST["image_multiselect"] as $image_id) {
                    try {
                        $image = Image::by_id($image_id);
                        $image->delete();
                    } catch (DoesNotExistError $e) {
                        continue;
                    }
                }

                $ste->vars["success"] = $translation["images_deleted"];
            }

            $images = Image::all();

            $ste->vars["images"] = array_map(function ($img) {
                return [
                "id"   => $img->get_id(),
                "name" => $img->name,
                "file" => $img->get_filename()
            ];
            }, $images);

            echo $ste->exectemplate("/systemtemplates/image_list.html");
        },
        "comments" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation;

            list($comment_id) = $url_next;

            $url_next = [];

            $ste->vars["section"]   = "content";
            $ste->vars["submenu"]   = "comments";
            $ste->vars["pagetitle"] = $translation["menu_comments"];

            /* Single comment? */
            if (!empty($comment_id)) {
                try {
                    $comment = Comment::by_id($comment_id);
                } catch (DoesNotExistError $e) {
                    throw new NotFoundError();
                }

                if (!$comment->read_by_admin) {
                    $comment->read_by_admin = true;
                    $comment->save();
                }

                if (isset($_POST["action_on_comment"])) {
                    switch ($_POST["action_on_comment"]) {
                        case "delete":
                            $comment->delete();
                            $ste->vars["success"] = $translation["comment_successfully_deleted"];
                            goto backend_content_comments_overview;
                            break;
                        case "make_visible":
                            $comment->visible = true;
                            $comment->save();
                            $ste->vars["success"] = $translation["comment_successfully_made_visible"];
                            break;
                        case "make_invisible":
                            $comment->visible = false;
                            $comment->save();
                            $ste->vars["success"] = $translation["comment_successfully_made_invisible"];
                            break;
                    }
                }

                $ste->vars["id"] = $comment->get_id();
                $ste->vars["visible"] = $comment->visible;
                $ste->vars["article"] = $comment->get_article()->urlname;
                $ste->vars["language"] = $comment->get_language();
                $ste->vars["date"] = $comment->get_timestamp();
                $ste->vars["author"] = "\"{$comment->author_name}\" <{$comment->author_mail}>";
                $ste->vars["comment_text"] = $comment->create_html();
                $ste->vars["comment_raw"] = $comment->text;

                echo $ste->exectemplate("/systemtemplates/single_comment.html");
                return;
            }

            backend_content_comments_overview:

            /* Perform an action on all selected comments */
            if (!empty($_POST["action_on_comments"])) {
                switch ($_POST["action_on_comments"]) {
                    case "delete":
                        $commentaction = function ($c) {
                            $c->delete();
                        };
                        $ste->vars["success"] = $translation["comments_successfully_deleted"];
                        break;
                    case "mark_read":
                        $commentaction = function ($c) {
                            $c->read_by_admin = true;
                            $c->save();
                        };
                        $ste->vars["success"] = $translation["comments_successfully_marked_read"];
                        break;
                    case "mark_unread":
                        $commentaction = function ($c) {
                            $c->read_by_admin = false;
                            $c->save();
                        };
                        $ste->vars["success"] = $translation["comments_successfully_marked_unread"];
                        break;
                    case "make_visible":
                        $commentaction = function ($c) {
                            $c->visible = true;
                            $c->save();
                        };
                        $ste->vars["success"] = $translation["comments_successfully_made_visible"];
                        break;
                    case "make_invisible":
                        $commentaction = function ($c) {
                            $c->visible = false;
                            $c->save();
                        };
                        $ste->vars["success"] = $translation["comments_successfully_made_invisible"];
                        break;
                    default:
                        $ste->vars["error"] = $translation["unknown_action"];
                        break;
                }
                if (isset($commentaction)) {
                    foreach ($_POST["comment_multiselect"] as $c_id) {
                        try {
                            $comment = Comment::by_id($c_id);
                            $commentaction($comment);
                        } catch (DoesNotExistError $e) {
                            continue;
                        }
                    }
                }
            }

            $comments = Comment::all();

            /* Filtering */
            $filterquery = [];
            if (!empty($_GET["filter_article"])) {
                $searchfor = strtolower($_GET["filter_article"]);
                $comments = array_filter($comments, function ($c) use ($searchfor) {
                    return strpos(strtolower($c->get_article()->urlname), $searchfor) !== false;
                });
                $filterquery[] = "filter_article=" . urlencode($_GET["filter_article"]);
                $ste->vars["filter_article"] = $_GET["filter_article"];
            }
            $ste->vars["filterquery"] = implode("&", $filterquery);

            /* Sorting */
            if (isset($_GET["sort_asc"])) {
                $sort_dir = 1;
                $sort_by  = $_GET["sort_asc"];
            } elseif (isset($_GET["sort_desc"])) {
                $sort_dir = -1;
                $sort_by  = $_GET["sort_desc"];
            } else {
                $sort_dir = 1;
                $sort_by  = "was_read";
            }

            switch ($sort_by) {
                case "language":
                    usort($comments, function ($a, $b) use ($sort_dir) {
                        return strcmp($a->get_language(), $b->get_language()) * $sort_dir;
                    });
                    break;
                case "date":
                    usort($comments, function ($a, $b) use ($sort_dir) {
                        return intcmp($a->get_timestamp(), $b->get_timestamp()) * $sort_dir;
                    });
                    break;
                case "was_read":
                default:
                    usort($comments, function ($a, $b) use ($sort_dir) {
                        return intcmp((int) $a->read_by_admin, (int) $b->read_by_admin) * $sort_dir;
                    });
                    $sort_by = "was_read";
                    break;
            }
            $ste->vars["sortquery"] = "sort_" . ($sort_dir == 1 ? "asc" : "desc") . "=$sort_by";
            $ste->vars["sorting"] = ["dir" => ($sort_dir == 1 ? "asc" : "desc"), "by" => $sort_by];
            $ste->vars["sort_" . ($sort_dir == 1 ? "asc" : "desc") . "_$sort_by"] = true;

            $ste->vars["comments"] = array_map(function ($c) {
                return [
                "id" => $c->get_id(),
                "visible" => $c->visible,
                "read_by_admin" => $c->read_by_admin,
                "article" => $c->get_article()->urlname,
                "excerpt" => substr(str_replace(["\r\n", "\n", "\r"], " ", $c->text), 0, 50),
                "language" => $c->get_language(),
                "date" => $c->get_timestamp(),
                "author" => "\"{$c->author_name}\" <{$c->author_mail}>"
            ];
            }, $comments);

            echo $ste->exectemplate("/systemtemplates/comments_list.html");
        }
    ]),
    "design" => url_action_subactions([
        "templates" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation;

            list($template) = $url_next;

            $url_next = [];

            $ste->vars["section"]   = "design";
            $ste->vars["submenu"]   = "templates";
            $ste->vars["pagetitle"] = $translation["menu_templates"];

            if (isset($template)) {
                if (preg_match("/^[a-zA-Z0-9\\-_\\.]+$/", $template) == 0) { /* Prevent a possible LFI attack. */
                    throw new NotFoundError();
                }
                if (!is_file(SITE_BASE_PATH . "/ratatoeskr/templates/src/usertemplates/$template")) {
                    throw new NotFoundError();
                }
                $ste->vars["template_name"] = $template;
                $ste->vars["template_code"] = file_get_contents(SITE_BASE_PATH . "/ratatoeskr/templates/src/usertemplates/$template");
            }

            /* Was there a delete request? */
            if (isset($_POST["delete"]) and ($_POST["really_delete"] == "yes")) {
                foreach ($_POST["templates_multiselect"] as $tplname) {
                    if (preg_match("/^[a-zA-Z0-9\\-_\\.]+$/", $tplname) == 0) { /* Prevent a possible LFI attack. */
                        continue;
                    }
                    if (is_file(SITE_BASE_PATH . "/ratatoeskr/templates/src/usertemplates/$tplname")) {
                        @unlink(SITE_BASE_PATH . "/ratatoeskr/templates/src/usertemplates/$tplname");
                    }
                }
                $ste->vars["success"] = $translation["templates_successfully_deleted"];
            }

            /* A write request? */
            if (isset($_POST["save_template"])) {
                if (preg_match("/^[a-zA-Z0-9\\-_\\.]+$/", $_POST["template_name"]) == 1) {
                    $ste->vars["template_name"] = $_POST["template_name"];
                    $ste->vars["template_code"] = $_POST["template_code"];

                    try {
                        \ste\transcompile(\ste\parse(\ste\precompile($_POST["template_code"]), $_POST["template_name"]));
                        file_put_contents(SITE_BASE_PATH . "/ratatoeskr/templates/src/usertemplates/" . $_POST["template_name"], $_POST["template_code"]);
                        $ste->vars["success"] = $translation["template_successfully_saved"];
                    } catch (\ste\ParseCompileError $e) {
                        $e->rewrite($_POST["template_code"]);
                        $ste->vars["error"] = $translation["could_not_compile_template"] . $e->getMessage();
                    }
                } else {
                    $ste->vars["error"] = $translation["invalid_template_name"];
                }
            }

            /* Get all templates */
            $ste->vars["templates"] = [];
            $tpldir = new DirectoryIterator(SITE_BASE_PATH . "/ratatoeskr/templates/src/usertemplates");
            foreach ($tpldir as $fo) {
                if ($fo->isFile()) {
                    $ste->vars["templates"][] = $fo->getFilename();
                }
            }
            sort($ste->vars["templates"]);

            echo $ste->exectemplate("/systemtemplates/templates.html");
        },
        "styles" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation;

            list($style) = $url_next;

            $url_next = [];

            $ste->vars["section"]   = "design";
            $ste->vars["submenu"]   = "styles";
            $ste->vars["pagetitle"] = $translation["menu_styles"];

            if (isset($style)) {
                try {
                    $style = Style::by_name($style);
                    $ste->vars["style_name"] = $style->name;
                    $ste->vars["style_code"] = $style->code;
                } catch (DoesNotExistError $e) {
                    throw new NotFoundError();
                }
            }

            /* Was there a delete request? */
            if (isset($_POST["delete"]) and ($_POST["really_delete"] == "yes")) {
                foreach ($_POST["styles_multiselect"] as $stylename) {
                    try {
                        $style = Style::by_name($stylename);
                        $style->delete();
                    } catch (DoesNotExistError $e) {
                        continue;
                    }
                }
                $ste->vars["success"] = $translation["styles_successfully_deleted"];
            }

            /* A write request? */
            if (isset($_POST["save_style"])) {
                if (Style::test_name($_POST["style_name"])) {
                    $ste->vars["style_name"] = $_POST["style_name"];
                    $ste->vars["style_code"] = $_POST["style_code"];

                    try {
                        $style = Style::by_name($_POST["style_name"]);
                    } catch (DoesNotExistError $e) {
                        $style = Style::create($_POST["style_name"]);
                    }

                    $style->code = $_POST["style_code"];
                    $style->save();

                    $ste->vars["success"] = $translation["style_successfully_saved"];
                } else {
                    $ste->vars["error"] = $translation["invalid_style_name"];
                }
            }

            /* Get all styles */
            $ste->vars["styles"] = array_map(function ($s) {
                return $s->name;
            }, Style::all());
            sort($ste->vars["styles"]);

            echo $ste->exectemplate("/systemtemplates/styles.html");
        },
        "sections" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation, $languages, $ratatoeskr_settings;

            $url_next = [];

            $ste->vars["section"]   = "design";
            $ste->vars["submenu"]   = "sections";
            $ste->vars["pagetitle"] = $translation["menu_pagesections"];

            /* New section? */
            if (isset($_POST["new_section"])) {
                try {
                    Section::by_name($_POST["section_name"]);
                    $ste->vars["error"] = $translation["section_already_exists"];
                } catch (DoesNotExistError $e) {
                    if ((preg_match("/^[a-zA-Z0-9\\-_\\.]+$/", $_POST["template"]) == 0) or (!is_file(SITE_BASE_PATH . "/ratatoeskr/templates/src/usertemplates/{$_POST['template']}"))) {
                        $ste->vars["error"] = $translation["unknown_template"];
                    } elseif (!Section::test_name($_POST["section_name"])) {
                        $ste->vars["error"] = $translation["invalid_section_name"];
                    } else {
                        $section = Section::create($_POST["section_name"]);
                        $section->template = $_POST["template"];
                        $section->title[$data["user"]->language] = new Translation($_POST["section_name"], "");
                        $section->save();
                        $ste->vars["success"] = $translation["section_created_successfully"];
                    }
                }
            }

            /* Remove a style? */
            if (isset($_GET["rmstyle"]) and isset($_GET["rmfrom"])) {
                try {
                    $section = Section::by_name($_GET["rmfrom"]);
                    $style   = Style::by_name($_GET["rmstyle"]);
                    $section->remove_style($style);
                    $section->save();
                    $ste->vars["success"] = $translation["style_removed"];
                } catch (DoesNotExistError $e) {
                }
            }

            /* Delete a section? */
            if (isset($_POST["delete"]) and (@$_POST["really_delete"] == "yes") and isset($_POST["section_select"])) {
                try {
                    $section = Section::by_name($_POST["section_select"]);
                    if ($section->get_id() == $ratatoeskr_settings["default_section"]) {
                        $ste->vars["error"] = $translation["cannot_delete_default_section"];
                    } else {
                        $default_section = Section::by_id($ratatoeskr_settings["default_section"]);
                        foreach ($section->get_articles() as $article) {
                            $article->set_section($default_section);
                            $article->save();
                        }
                        $section->delete();
                        $ste->vars["success"] = $translation["section_successfully_deleted"];
                    }
                } catch (DoesNotExistError $e) {
                }
            }

            /* Make section default? */
            if (isset($_POST["make_default"]) and isset($_POST["section_select"])) {
                try {
                    $section = Section::by_name($_POST["section_select"]);
                    $ratatoeskr_settings["default_section"] = $section->get_id();
                    $ste->vars["success"] = $translation["default_section_changed_successfully"];
                } catch (DoesNotExistError $e) {
                }
            }

            /* Set template? */
            if (isset($_POST["set_template"]) and isset($_POST["section_select"])) {
                try {
                    $section = Section::by_name($_POST["section_select"]);
                    if ((preg_match("/^[a-zA-Z0-9\\-_\\.]+$/", $_POST["set_template_to"]) == 0) or (!is_file(SITE_BASE_PATH . "/ratatoeskr/templates/src/usertemplates/{$_POST['set_template_to']}"))) {
                        $ste->vars["error"] = $translation["unknown_template"];
                    } else {
                        $section->template = $_POST["set_template_to"];
                        $section->save();
                        $ste->vars["success"] = $translation["successfully_set_template"];
                    }
                } catch (DoesNotExistError $e) {
                }
            }

            /* Adding a style? */
            if (isset($_POST["add_style"]) and isset($_POST["section_select"])) {
                try {
                    $section = Section::by_name($_POST["section_select"]);
                    $style   = Style::by_name($_POST["style_to_add"]);
                    $section->add_style($style);
                    $ste->vars["success"] = $translation["successfully_added_style"];
                } catch (DoesNotExistError $e) {
                }
            }

            /* Set/unset title? */
            if (isset($_POST["set_title"]) and isset($_POST["section_select"])) {
                if (!isset($languages[$_POST["set_title_lang"]])) {
                    $ste->vars["error"] = $translation["language_unknown"];
                } else {
                    try {
                        $section = Section::by_name($_POST["section_select"]);
                        if (!empty($_POST["set_title_text"])) {
                            $section->title[$_POST["set_title_lang"]] = new Translation($_POST["set_title_text"], "");
                        } elseif (isset($section->title[$_POST["set_title_lang"]])) {
                            unset($section->title[$_POST["set_title_lang"]]);
                        }
                        $section->save();
                        $ste->vars["success"] = $translation["successfully_set_section_title"];
                    } catch (DoesNotExistError $e) {
                    }
                }
            }

            /* Get all templates */
            $ste->vars["templates"] = [];
            $tpldir = new DirectoryIterator(SITE_BASE_PATH . "/ratatoeskr/templates/src/usertemplates");
            foreach ($tpldir as $fo) {
                if ($fo->isFile()) {
                    $ste->vars["templates"][] = $fo->getFilename();
                }
            }
            sort($ste->vars["templates"]);

            /* Get all styles */
            $ste->vars["styles"] = array_map(function ($s) {
                return $s->name;
            }, Style::all());
            sort($ste->vars["styles"]);

            /* Get all sections */
            $sections = Section::all();
            $ste->vars["sections"] = array_map(function ($section) use ($ratatoeskr_settings) {
                $titles = [];
                foreach ($section->title as $l => $t) {
                    $titles[$l] = $t->text;
                }
                return [
                    "name"     => $section->name,
                    "title"    => $titles,
                    "template" => $section->template,
                    "styles"   => array_map(function ($style) {
                        return $style->name;
                    }, $section->get_styles()),
                    "default"  => ($section->get_id() == $ratatoeskr_settings["default_section"])
                ];
            }, $sections);

            echo $ste->exectemplate("/systemtemplates/sections.html");
        }
    ]),
    "admin" => url_action_subactions([
        "settings" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation, $languages, $ratatoeskr_settings, $textprocessors;

            $url_next = [];

            $ste->vars["section"]   = "admin";
            $ste->vars["submenu"]   = "settings";
            $ste->vars["pagetitle"] = $translation["menu_settings"];

            $ste->vars["textprocessors"] = [];
            foreach ($textprocessors as $txtproc => $properties) {
                if ($properties[1]) {
                    $ste->vars["textprocessors"][] = $txtproc;
                }
            }

            /* Toggle debugmode value? */
            if (isset($_POST["toggle_debugmode"])) {
                if (isset($ratatoeskr_settings["debugmode"]) and $ratatoeskr_settings["debugmode"]) {
                    $ratatoeskr_settings["debugmode"] = false;
                    $ste->vars["success"] = $translation["debugmode_now_disabled"];
                } else {
                    $ratatoeskr_settings["debugmode"] = true;
                    $ste->vars["success"] = $translation["debugmode_now_enabled"];
                }
            }

            /* Save comment settings? */
            if (isset($_POST["save_comment_settings"])) {
                if (!in_array(@$_POST["comment_textprocessor"], $ste->vars["textprocessors"])) {
                    $ste->vars["error"] = $translation["unknown_txtproc"];
                } else {
                    $ratatoeskr_settings["comment_textprocessor"]   = $_POST["comment_textprocessor"];
                    $ratatoeskr_settings["comment_visible_default"] = (isset($_POST["comment_auto_visible"]) and ($_POST["comment_auto_visible"] == "yes"));
                    $ste->vars["success"] = $translation["comment_settings_successfully_saved"];
                }
            }

            /* Delete language? */
            if (isset($_POST["delete"]) and ($_POST["really_delete"] == "yes") and isset($_POST["language_select"])) {
                if ($ratatoeskr_settings["default_language"] == $_POST["language_select"]) {
                    $ste->vars["error"] = $translation["cannot_delete_default_language"];
                } else {
                    $ratatoeskr_settings["languages"] = array_filter($ratatoeskr_settings["languages"], function ($l) {
                        return $l != $_POST["language_select"];
                    });
                    $ste->vars["success"] = $translation["language_successfully_deleted"];
                }
            }

            /* Set default language */
            if (isset($_POST["make_default"]) and isset($_POST["language_select"])) {
                if (in_array($_POST["language_select"], $ratatoeskr_settings["languages"])) {
                    $ratatoeskr_settings["default_language"] = $_POST["language_select"];
                    $ste->vars["success"] = $translation["successfully_set_default_language"];
                }
            }

            /* Add a language */
            if (isset($_POST["add_language"])) {
                if (!isset($languages[$_POST["language_to_add"]])) {
                    $ste->vars["error"] = $translation["language_unknown"];
                } else {
                    if (!in_array($_POST["language_to_add"], $ratatoeskr_settings["languages"])) {
                        $ls = $ratatoeskr_settings["languages"];
                        $ls[] = $_POST["language_to_add"];
                        $ratatoeskr_settings["languages"] = $ls;
                    }
                    $ste->vars["success"] = $translation["language_successfully_added"];
                }
            }

            $ste->vars["debugmode_enabled"]     = (isset($ratatoeskr_settings["debugmode"]) and $ratatoeskr_settings["debugmode"]);
            $ste->vars["comment_auto_visible"]  = $ratatoeskr_settings["comment_visible_default"];
            $ste->vars["comment_textprocessor"] = $ratatoeskr_settings["comment_textprocessor"];
            $ste->vars["used_langs"] = array_map(function ($l) use ($ratatoeskr_settings, $languages) {
                return [
                "code"    => $l,
                "name"    => $languages[$l]["language"],
                "default" => ($l == $ratatoeskr_settings["default_language"])
            ];
            }, $ratatoeskr_settings["languages"]);

            echo $ste->exectemplate("/systemtemplates/settings.html");
        },
        "users" => url_action_subactions([
            "_index" => function (&$data, $url_now, &$url_next) {
                global $ste, $translation;

                $url_next = [];

                $ste->vars["section"]   = "admin";
                $ste->vars["submenu"]   = "users";
                $ste->vars["pagetitle"] = $translation["menu_users_groups"];

                /* Add a new group? */
                if (isset($_POST["new_group"])) {
                    if (empty($_POST["group_name"])) {
                        $ste->vars["error"] = $translation["empty_group_name"];
                    } else {
                        try {
                            Group::by_name($_POST["group_name"]);
                            $ste->vars["error"] = $translation["group_already_exists"];
                        } catch (DoesNotExistError $e) {
                            Group::create($_POST["group_name"]);
                            $ste->vars["success"] = $translation["successfully_created_group"];
                        }
                    }
                }

                /* Add a new user? */
                if (isset($_POST["new_user"])) {
                    if (empty($_POST["username"])) {
                        $ste->vars["error"] = $translation["empty_username"];
                    } else {
                        try {
                            User::by_name($_POST["username"]);
                            $ste->vars["error"] = $translation["user_already_exists"];
                        } catch (DoesNotExistError $e) {
                            User::create($_POST["username"], PasswordHash::create($_POST["initial_password"]));
                            $ste->vars["success"] = $translation["successfully_created_user"];
                        }
                    }
                }

                /* Delete groups? */
                if (isset($_POST["delete_groups"]) and ($_POST["really_delete"] == "yes") and (!empty($_POST["groups_multiselect"]))) {
                    $deleted = 0;
                    foreach ($_POST["groups_multiselect"] as $gid) {
                        try {
                            $group = Group::by_id($gid);
                            if ($group->name == "admins") {
                                $ste->vars["error"] = $translation["cannot_delete_admin_group"];
                            } else {
                                $group->delete();
                                ++$deleted;
                            }
                        } catch (DoesNotExistError $e) {
                            continue;
                        }
                    }
                    if ($deleted > 0) {
                        $ste->vars["success"] = $translation["successfully_deleted_groups"];
                    }
                }

                /* Delete users? */
                if (isset($_POST["delete_users"]) and ($_POST["really_delete"] == "yes") and (!empty($_POST["users_multiselect"]))) {
                    $deleted = 0;
                    foreach ($_POST["users_multiselect"] as $uid) {
                        if ($uid == $data["user"]->get_id()) {
                            $ste->vars["error"] = $translation["cannot_delete_yourself"];
                        } else {
                            try {
                                $user = User::by_id($uid);
                                $user->delete();
                                ++$deleted;
                            } catch (DoesNotExistError $e) {
                                continue;
                            }
                        }
                    }
                    if ($deleted > 0) {
                        $ste->vars["success"] = $translation["successfully_deleted_users"];
                    }
                }

                /* Get all groups */
                $ste->vars["groups"] = array_map(function ($g) {
                    return [
                    "id"   => $g->get_id(),
                    "name" => $g->name
                ];
                }, Group::all());

                /* Get all users */
                $ste->vars["users"] = array_map(function ($u) {
                    return [
                    "id"       => $u->get_id(),
                    "name"     => $u->username,
                    "memberof" => array_map(function ($g) {
                        return $g->name;
                    }, $u->get_groups()),
                    "fullname" => $u->fullname,
                    "mail"     => $u->mail
                ];
                }, User::all());

                echo $ste->exectemplate("/systemtemplates/users.html");
            },
            "u" => function (&$data, $url_now, &$url_next) {
                global $ste, $translation;

                try {
                    $user = User::by_id($url_next[0]);
                } catch (DoesNotExistError $e) {
                    throw new NotFoundError();
                }

                $url_next = [];

                $ste->vars["section"]   = "admin";
                $ste->vars["submenu"]   = "users";
                $ste->vars["pagetitle"] = $user->username;

                /* Modify data? */
                if (isset($_POST["change_data"])) {
                    $user->fullname = $_POST["fullname"];
                    $user->mail     = $_POST["mail"];
                    $user->language = $_POST["lang"];

                    $current_groups = array_map(function ($g) {
                        return $g->get_id();
                    }, $user->get_groups());
                    $new_groups     = empty($_POST[groups_multiselect]) ? [] : $_POST["groups_multiselect"];
                    $groups_exclude = array_diff($current_groups, $new_groups);
                    $groups_include = array_diff($new_groups, $current_groups);

                    foreach ($groups_exclude as $gid) {
                        try {
                            $g = Group::by_id($gid);
                            $g->exclude_user($user);
                        } catch (DoesNotExistError $e) {
                            continue;
                        }
                    }

                    foreach ($groups_include as $gid) {
                        try {
                            $g = Group::by_id($gid);
                            $g->include_user($user);
                        } catch (DoesNotExistError $e) {
                            continue;
                        }
                    }

                    $user->save();

                    $ste->vars["success"] = $translation["successfully_modified_user"];
                }

                /* New Password? */
                if (isset($_POST["new_password"])) {
                    $pwhash = PasswordHash::create($_POST["password"]);
                    $user->pwhash = $pwhash;
                    if ($user->get_id() == $data["user"]->get_id()) {
                        $_SESSION["ratatoeskr_pwhash"] = $pwhash;
                    }
                    $user->save();

                    $ste->vars["success"] = $translation["successfully_set_new_password"];
                }

                /* Put data to STE */
                $ste->vars["u"] = [
                    "id"       => $user->get_id(),
                    "name"     => $user->username,
                    "fullname" => $user->fullname,
                    "mail"     => $user->mail,
                    "lang"     => $user->language
                ];
                $ste->vars["groups"] = array_map(function ($g) use ($user) {
                    return [
                    "id"     => $g->get_id(),
                    "name"   => $g->name,
                    "member" => $user->member_of($g)
                ];
                }, Group::all());

                echo $ste->exectemplate("/systemtemplates/user.html");
            }
        ]),
        "repos" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation;

            $url_next = [];

            $ste->vars["section"]   = "admin";
            $ste->vars["submenu"]   = "repos";
            $ste->vars["pagetitle"] = $translation["menu_plugin_repos"];

            /* Add a repo? */
            if (isset($_POST["add_repo"])) {
                try {
                    $repo = Repository::create($_POST["repo_baseurl"]);
                    $ste->vars["success"] = $translation["successfully_added_repo"];
                } catch (RepositoryUnreachableOrInvalid $e) {
                    $ste->vars["error"] = $translation["repository_unreachable_or_invalid"];
                }
            }

            /* Delete repos? */
            if (isset($_POST["delete_repos"]) and ($_POST["really_delete"] == "yes")) {
                foreach ($_POST["repos_multiselect"] as $repo_id) {
                    try {
                        $repo = Repository::by_id($repo_id);
                        $repo->delete();
                    } catch (DoesNotExistError $e) {
                        continue;
                    }
                }
                $ste->vars["success"] = $translation["repos_deleted"];
            }

            /* Force refresh? */
            if (isset($_POST["force_repo_refresh"])) {
                $failed = [];
                foreach ($_POST["repos_multiselect"] as $repo_id) {
                    try {
                        $repo = Repository::by_id($repo_id);
                        $repo->refresh(true);
                    } catch (DoesNotExistError $e) {
                        continue;
                    } catch (RepositoryUnreachableOrInvalid $e) {
                        $failed[] = $repo->get_name();
                    }
                }
                $ste->vars["success"] = $translation["successfully_refreshed_repos"];
                if (!empty($failed)) {
                    $ste->vars["error"] = str_replace("[[REPOS]]", implode(", ", $failed), $translation["repo_refresh_failed_on"]);
                }
            }

            /* Fill data */
            $all_repos = Repository::all();
            $ste->vars["repos"] = array_map(
                function ($r) {
                    try {
                        $r->refresh();
                    } catch (RepositoryUnreachableOrInvalid $e) {
                    }
                    return [
                    "id"          => $r->get_id(),
                    "name"        => $r->get_name(),
                    "description" => $r->get_description(),
                    "baseurl"     => $r->get_baseurl()
                ];
                },
                $all_repos
            );

            echo $ste->exectemplate("/systemtemplates/repos.html");
        }
    ]),
    "plugin" => url_action_subactions([
        "list" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation, $plugin_objs, $api_compat;

            $url_next = [];

            $ste->vars["section"]   = "plugins";
            $ste->vars["submenu"]   = "pluginlist";
            $ste->vars["pagetitle"] = $translation["menu_pluginlist"];

            /* Delete plugins? */
            if (isset($_POST["delete"]) and (($_POST["really_delete"] == "yes") or ($_POST["really_delete"] == "force")) and (!empty($_POST["plugins_multiselect"]))) {
                foreach ($_POST["plugins_multiselect"] as $pid) {
                    try {
                        $plugin = Plugin::by_id($pid);
                        if ($_POST["really_delete"] != "force") {
                            if (!isset($plugin_objs[$pid])) {
                                eval($plugin->code);
                                $plugin_objs[$pid] = new $plugin->classname($pid);
                            }
                            $plugin_objs[$pid]->uninstall();
                        }
                        $plugin->delete();
                    } catch (DoesNotExistError $e) {
                        continue;
                    }
                }

                $ste->vars["success"] = $translation["successfully_deleted_plugins"];
            }

            /* Activate or deactivate plugins? */
            if ((isset($_POST["activate"]) or isset($_POST["deactivate"])) and (!empty($_POST["plugins_multiselect"]))) {
                $api_incompat = [];
                $newstatus = isset($_POST["activate"]);
                foreach ($_POST["plugins_multiselect"] as $pid) {
                    try {
                        $plugin = Plugin::by_id($pid);
                        if (!$plugin->installed) {
                            continue;
                        }
                        if ($newstatus and (!in_array($plugin->api, $api_compat))) {
                            $api_incompat[] = $plugin->name . ("(ID: " . $plugin->get_id() . ")");
                            continue;
                        }
                        $plugin->active = $newstatus;
                        $plugin->save();
                        if ($newstatus and (!isset($plugin_objs[$pid]))) {
                            eval($plugin->code);
                            $plugin_objs[$pid] = new $plugin->classname($pid);
                            $plugin_objs[$pid]->init();
                        }
                    } catch (DoesNotExistError $e) {
                        continue;
                    }
                }

                $ste->vars["success"] = $translation[$newstatus ? "plugins_activated" : "plugins_deactivated"];

                if (!empty($api_incompat)) {
                    $ste->vars["error"] = htmlesc(str_replace("[[PLUGINS]]", implode(", ", $api_incompat), $translation["could_not_activate_plugin_api_incompat"]));
                }
            }

            $stream_ctx = stream_context_create(["http" => ["timeout" => 5]]);

            /* Update plugins? */
            if (isset($_POST["update"]) and (!empty($_POST["plugins_multiselect"]))) {
                $updated = [];
                foreach ($_POST["plugins_multiselect"] as $pid) {
                    try {
                        $plugin = Plugin::by_id($pid);
                        if (!empty($plugin->updatepath)) {
                            $update_info = @unserialize(@file_get_contents($plugin->updatepath, false, $stream_ctx));
                            if (is_array($update_info) and (($update_info["current-version"]+0) > ($plugin->versioncount+0))) {
                                $pkg = PluginPackage::load(@file_get_contents($update_info["dl-path"], false, $stream_ctx));
                                $plugin->fill_from_pluginpackage($pkg);
                                $plugin->update = true;
                                $plugin->save();
                                $updated[] = $plugin->name;
                            }
                        }
                    } catch (DoesNotExistError $e) {
                        continue;
                    } catch (InvalidPackage $e) {
                        continue;
                    }
                }

                if (empty($updated)) {
                    $ste->vars["success"] = $translation["nothing_to_update"];
                } else {
                    $ste->vars["success"] = str_replace("[[PLUGINS]]", implode(", ", $updated), $translation["successfully_updated_plugins"]);
                }
            }

            /* Load plugin data */
            $all_plugins = Plugin::all();
            $ste->vars["plugins"] = [];
            $api_incompat = [];
            foreach ($all_plugins as $p) {
                if (!$p->installed) {
                    continue;
                }

                if (!in_array($p->api, $api_compat)) {
                    $api_incompat[] = $p->name . ("(ID: " . $p->get_id() . ")");
                }

                $ste->vars["plugins"][] = [
                    "id"          => $p->get_id(),
                    "name"        => $p->name,
                    "versiontext" => $p->versiontext,
                    "active"      => $p->active,
                    "description" => $p->short_description,
                    "web"         => $p->web,
                    "author"      => $p->author,
                    "help"        => !empty($p->help)
                ];
            }

            if (!empty($api_incompat)) {
                $ste->vars["notice"] = htmlesc(str_replace("[[PLUGINS]]", implode(", ", $api_incompat), $translation["plugins_incompat"]));
            }

            echo $ste->exectemplate("/systemtemplates/pluginlist.html");
        },
        "help" => function (&$data, $url_now, &$url_next) {
            global $ste;

            try {
                $plugin = Plugin::by_id($url_next[0]);
                if (empty($plugin->help)) {
                    throw new NotFoundError();
                }
            } catch (DoesNotExistError $e) {
                throw new NotFoundError();
            }

            $url_next = [];

            $ste->vars["section"]   = "plugins";
            $ste->vars["submenu"]   = "";
            $ste->vars["pagetitle"] = $plugin->name;
            $ste->vars["help"]      = $plugin->help;

            echo $ste->exectemplate("/systemtemplates/pluginhelp.html");
        },
        "install" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation, $api_compat;

            $url_next = [];

            $ste->vars["section"]   = "plugins";
            $ste->vars["submenu"]   = "installplugins";
            $ste->vars["pagetitle"] = $translation["menu_plugininstall"];

            $all_repos = Repository::all();
            foreach ($all_repos as $repo) {
                try {
                    $repo->refresh();
                } catch (RepositoryUnreachableOrInvalid $e) {
                    continue;
                }
            }

            if (isset($_POST["installpackage"])) {
                if (is_uploaded_file($_FILES["pluginpackage"]["tmp_name"])) {
                    try {
                        $package = PluginPackage::load(file_get_contents($_FILES["pluginpackage"]["tmp_name"]));
                        unlink($_FILES["pluginpackage"]["tmp_name"]);
                        if (in_array($package->api, $api_compat)) {
                            $plugin = Plugin::create();
                            $plugin->fill_from_pluginpackage($package);
                            $plugin->installed = false;
                            $plugin->active = false;
                            $plugin->save();
                            $url_next = ["confirminstall", (string) $plugin->get_id()];
                            return;
                        } else {
                            $ste->vars["error"] = str_replace("[[API]]", $package->api, $translation["incompatible_plugin"]);
                        }
                    } catch (InvalidPackage $e) {
                        $ste->vars["error"] = $translation["invalid_package"];
                        unlink($_FILES["pluginpackage"]["tmp_name"]);
                    }
                } else {
                    $ste->vars["error"] = $translation["upload_failed"];
                }
            }

            if (isset($_POST["search_in_repos"])) {
                $ste->vars["searchresults"] = [];
                $repos_to_scan = ($_POST["searchin"] == "*") ? $all_repos : Repository::by_id($_POST["searchin"]);
                $searchfor = strtolower($_POST["searchfor"]);
                foreach ($repos_to_scan as $repo) {
                    foreach ($repo->packages as $pkg) {
                        if (empty($searchfor) or (strpos(strtolower($pkg[0]), $searchfor) !== false) or (strpos(strtolower($pkg[2]), $searchfor) !== false)) {
                            $ste->vars["searchresults"][] = [
                                "name"        => $pkg[0],
                                "description" => $pkg[2],
                                "reponame"    => $repo->get_name(),
                                "repoid"      => $repo->get_id()
                            ];
                        }
                    }
                }
            }

            $ste->vars["repos"] = array_map(function ($r) {
                return [
                "id"   => $r->get_id(),
                "name" => $r->get_name()
            ];
            }, $all_repos);

            echo $ste->exectemplate("/systemtemplates/plugininstall.html");
        },
        "repoinstall" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation;

            try {
                $repo = Repository::by_id($_GET["repo"]);
                $pkg = $repo->download_package($_GET["pkg"]);
                $plugin = Plugin::create();
                $plugin->fill_from_pluginpackage($pkg);
                $plugin->installed = false;
                $plugin->active = false;
                $plugin->save();
                $url_next = ["confirminstall", (string) $plugin->get_id()];
            } catch (DoesNotExistError $e) {
                $ste->vars["error"] = $translation["package_or_repo_not_found"];
                $url_next = ["install"];
            } catch (InvalidPackage $e) {
                $ste->vars["error"] = $translation["invalid_package"];
                $url_next = ["install"];
            }
        },
        "confirminstall" => function (&$data, $url_now, &$url_next) {
            global $ste, $translation;

            list($plugin_id) = $url_next;
            $url_next = [];

            $ste->vars["section"]   = "plugins";
            $ste->vars["submenu"]   = "installplugins";
            $ste->vars["pagetitle"] = $translation["menu_plugininstall"];

            try {
                $plugin = Plugin::by_id($plugin_id);
            } catch (DoesNotExistError $e) {
                throw new NotFoundError();
            }

            if ($plugin->installed) {
                throw new NotFoundError();
            }

            $ste->vars["plugin_id"]   = $plugin->get_id();
            $ste->vars["name"]        = $plugin->name;
            $ste->vars["description"] = $plugin->short_description;
            $ste->vars["code"]        = $plugin->code;
            $ste->vars["license"]     = $plugin->license;

            if (isset($_POST["yes"])) {
                $plugin->installed = true;
                $plugin->save();
                eval($plugin->code);
                $plugin_instance = new $plugin->classname($plugin->get_id());
                $plugin_instance->install();
                $ste->vars["success"] = $translation["plugin_installed_successfully"];
                $url_next = ["list"];
                return;
            }

            if (isset($_POST["no"])) {
                $plugin->delete();
                $url_next = ["install"];
                return;
            }

            echo $ste->exectemplate("/systemtemplates/confirminstall.html");
        }
    ]),
    "pluginpages" => url_action_subactions($pluginpages_handlers)
]);
}
