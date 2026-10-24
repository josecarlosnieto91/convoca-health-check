<?php
/**
 * Bootstrap de las pruebas unitarias.
 *
 * `lib/` es lógica pura: no necesita WordPress. Si algún día deja de serlo, esto fallará y habrá que decidir.
 *
 * @package Convoca\HealthCheck
 */

require_once dirname( __DIR__ ) . '/lib/cobertura.php';
require_once dirname( __DIR__ ) . '/lib/capacidades.php';
