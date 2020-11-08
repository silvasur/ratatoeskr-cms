<?php

namespace r7r\cms\sys;

use r7r\ste\STECore;
use r7r\ste\FilesystemStorageAccess;

/**
 * Manages the global STE instance
 */
class GlobalSte
{
    /** @var STECore */
    private $ste;

    /** @var self|null */
    private static $instance = null;

    private function __construct()
    {
        $tpl_basedir = dirname(__FILE__) . "/../templates";

        $this->ste = new STECore(new FilesystemStorageAccess("$tpl_basedir/src", "$tpl_basedir/transc"));
        if (defined("__DEBUG__") && __DEBUG__) {
            $this->ste->mute_runtime_errors = false;
        }

        $this->ste->register_tag("l10n_replace", [self::class, "tag_l10n_replace"]);
        $this->ste->register_tag("capitalize", [self::class, "tag_capitalize"]);
        $this->ste->register_tag("loremipsum", [self::class, "tag_loremipsum"]);
    }

    public static function tag_l10n_replace($ste, $params, $sub)
    {
        $content = $sub($ste);
        foreach ($params as $name => $replace) {
            $content = str_replace("[[$name]]", $replace, $content);
        }
        return $content;
    }

    public static function tag_capitalize($ste, $params, $sub)
    {
        return ucwords($sub($ste));
    }

    public static function tag_loremipsum($ste, $params, $sub)
    {
        $repeats = empty($params["repeat"]) ? 1 : $params["repeat"] + 0;
        return implode(
            "\n\n",
            array_fill(
                0,
                $repeats,
                "<p>Lorem ipsum dolor sit amet, consectetur adipisici elit, "
                . "sed eiusmod tempor incidunt ut labore et dolore magna "
                . "aliqua. Ut enim ad minim veniam, quis nostrud exercitation "
                . "ullamco laboris nisi ut aliquid ex ea commodi consequat. "
                . "Quis aute iure reprehenderit in voluptate velit esse cillum "
                . "dolore eu fugiat nulla pariatur. Excepteur sint obcaecat "
                . "cupiditat non proident, sunt in culpa qui officia deserunt "
                . "mollit anim id est laborum.</p>\n\n<p>Duis autem vel eum "
                . "iriure dolor in hendrerit in vulputate velit esse molestie "
                . "consequat, vel illum dolore eu feugiat nulla facilisis at "
                . "vero eros et accumsan et iusto odio dignissim qui blandit "
                . "praesent luptatum zzril delenit augue duis dolore te "
                . "feugait nulla facilisi. Lorem ipsum dolor sit amet, "
                . "consectetuer adipiscing elit, sed diam nonummy nibh euismod "
                . "tincidunt ut laoreet dolore magna aliquam erat volutpat."
                . "</p>\n\n<p>Ut wisi enim ad minim veniam, quis nostrud "
                . "exerci tation ullamcorper suscipit lobortis nisl ut aliquip "
                . "ex ea commodo consequat. Duis autem vel eum iriure dolor in "
                . "hendrerit in vulputate velit esse molestie consequat, vel "
                . "illum dolore eu feugiat nulla facilisis at vero eros et "
                . "accumsan et iusto odio dignissim qui blandit praesent "
                . "luptatum zzril delenit augue duis dolore te feugait nulla "
                . "facilisi.</p>\n\n<p>Nam liber tempor cum soluta nobis "
                . "eleifend option congue nihil imperdiet doming id quod mazim "
                . "placerat facer possim assum. Lorem ipsum dolor sit amet, "
                . "consectetuer adipiscing elit, sed diam nonummy nibh euismod "
                . "tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut "
                . "wisi enim ad minim veniam, quis nostrud exerci tation "
                . "ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo "
                . "consequat.</p>\n\n<p>Duis autem vel eum iriure dolor in "
                . "hendrerit in vulputate velit esse molestie consequat, vel "
                . "illum dolore eu feugiat nulla facilisis.</p>\n\n<p>At vero "
                . "eos et accusam et justo duo dolores et ea rebum. Stet clita "
                . "kasd gubergren, no sea takimata sanctus est Lorem ipsum "
                . "dolor sit amet. Lorem ipsum dolor sit amet, consetetur "
                . "sadipscing elitr, sed diam nonumy eirmod tempor invidunt "
                . "ut labore et dolore magna aliquyam erat, sed diam voluptua. "
                . "At vero eos et accusam et justo duo dolores et ea rebum. "
                . "Stet clita kasd gubergren, no sea takimata sanctus est "
                . "Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, "
                . "consetetur sadipscing elitr, At accusam aliquyam diam diam "
                . "dolore dolores duo eirmod eos erat, et nonumy sed tempor et "
                . "et invidunt justo labore Stet clita ea et gubergren, kasd "
                . "magna no rebum. sanctus sea sed takimata ut vero voluptua. "
                . "est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, "
                . "consetetur sadipscing elitr, sed diam nonumy eirmod tempor "
                . "invidunt ut labore et dolore magna aliquyam erat.</p>\n\n"
                . "<p>Consetetur sadipscing elitr, sed diam nonumy eirmod "
                . "tempor invidunt ut labore et dolore magna aliquyam erat, "
                . "sed diam voluptua. At vero eos et accusam et justo duo "
                . "dolores et ea rebum. Stet clita kasd gubergren, no sea "
                . "takimata sanctus est Lorem ipsum dolor sit amet. Lorem "
                . "ipsum dolor sit amet, consetetur sadipscing elitr, sed "
                . "diam nonumy eirmod tempor invidunt ut labore et dolore "
                . "magna aliquyam erat, sed diam voluptua. At vero eos et "
                . "accusam et justo duo dolores et ea rebum. Stet clita kasd "
                . "gubergren, no sea takimata sanctus est Lorem ipsum dolor "
                . "sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing "
                . "elitr, sed diam nonumy eirmod tempor invidunt ut labore et "
                . "dolore magna aliquyam erat, sed diam voluptua. At vero eos "
                . "et accusam et justo duo dolores et ea rebum. Stet clita "
                . "kasd gubergren, no sea takimata sanctus est Lorem ipsum "
                . "dolor sit amet.</p>"
            )
        );
    }

    private static function getInstance(): self
    {
        self::$instance = self::$instance ?? new self();
        return self::$instance;
    }

    /**
     * Get (and initialize, if necessary) the global STE instance.
     * @return STECore
     */
    public static function getGlobalSte(): STECore
    {
        return self::getInstance()->ste;
    }
}
