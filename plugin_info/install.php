<?php
require_once __DIR__ . '/../../../core/php/core.inc.php';

function meteopollen_install() {
  meteopollen::avertir();
}

function meteopollen_update() {
  foreach (eqLogic::byType('meteopollen') as $eq) $eq->save(); // crée les commandes ajoutées par la nouvelle version
  meteopollen::avertir();
}

function meteopollen_remove() {
}
