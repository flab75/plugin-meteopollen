<?php
/* Contrôles sans effet sur la box : niveaux de pluie, données de chaque tuile.
   Usage : sudo -u www-data php tests/selftest.php */
require_once __DIR__ . '/../core/class/meteopollen.class.php';
$ok = function ($v, $m) { if (!$v) { echo "ECHEC : $m\n"; exit(1); } }; // assert() est désactivé en production

$ok([meteopollen::niveau(0), meteopollen::niveau(0.1), meteopollen::niveau(2.5), meteopollen::niveau(8)] == [1, 2, 3, 4], 'niveaux de pluie');
echo "niveaux OK\n";

$a = ['geoloc' => '7', 'ville' => 'Noisy-le-Sec', 'zip' => '93130', 'insee' => '93053', 'lat' => 48.89, 'lon' => 2.45, 'numDept' => '93', 'bulletinCote' => 0];
$ok(meteopollen::localisation($a) == array_diff_key($a, ['geoloc' => 0]), 'localisation complète copiée');
$ok(meteopollen::localisation(['lat' => '', 'lon' => ''] + $a) === null, 'localisation vidée : pas de copie');
echo "localisation OK\n";

$admin = null; foreach (user::all() as $u) if ($u->getProfils() == 'admin') { $admin = $u; break; }
foreach (eqLogic::byType('meteopollen') as $eq) {
  $d = $eq->donnees($admin);
  $ok(is_array($d['c']) && is_array($d['k']) && json_encode($d, JSON_HEX_TAG) !== false, 'données de ' . $eq->getHumanName());
  $ok($eq->getConfiguration('type') == 'pollen' || count($d['c']) > 9, 'équipement lu sans commandes : ' . $eq->getHumanName());
  echo 'données OK : ' . $eq->getHumanName() . ' (' . $eq->getConfiguration('type') . ', ' . count($d['c']) . " commandes)\n";
}
echo "TOUT OK\n";
