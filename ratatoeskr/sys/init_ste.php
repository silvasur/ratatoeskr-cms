<?php

/*
 * File: ratatoeskr/sys/init_ste.php
 *
 * When included, the file will initialize the global STECore instance.
 *
 * License:
 * This file is part of Ratatöskr.
 * Ratatöskr is licensed unter the MIT / X11 License.
 * See "ratatoeskr/licenses/ratatoeskr" for more information.
 */

use r7r\cms\sys\GlobalSte;
use r7r\ste\STECore;

/**
 * @var STECore The global STECore instance.
 * @deprecated Use {@see GlobalSte} directly now.
 */
$ste = GlobalSte::getGlobalSte();
