<?php
/**
 * Clases del núcleo que el health check comprueba que EXISTEN (`Webhook_Manager`, `Module_Registry`).
 *
 * Son dobles de integración vacíos: aquí solo importa que `class_exists()` diga la verdad, porque el health check
 * lo usa como comprobación de que el plugin del núcleo está completo.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\Core;

/**
 * Gestor de webhooks del núcleo.
 */
class Webhook_Manager {}

/**
 * Registro de módulos del núcleo.
 */
class Module_Registry {}
