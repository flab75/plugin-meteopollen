<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';

/* Une tuile = un équipement (configuration « type ») :
   pollen : commandes propres remplies chaque heure (Open-Meteo / Copernicus CAMS) ;
   meteo  : lit un équipement Météo-France, plus ses commandes propres (risque du jour / demain, pluie Open-Meteo dans l'heure). */
class meteopollen extends eqLogic {
  const POLLENS = ['alder' => 'Aulne', 'birch' => 'Bouleau', 'olive' => 'Olivier', 'grass' => 'Graminées', 'mugwort' => 'Armoise', 'ragweed' => 'Ambroisie'];
  const SOURCE = ['meteo' => 'meteofrance']; // type de tuile → plugin de l'équipement lu
  const LOC = ['ville', 'zip', 'insee', 'lat', 'lon', 'numDept', 'bulletinCote']; // localisation d'un équipement Météo-France

  private static function chaque($type, $f) { // une tuile en erreur n'empêche pas les autres
    foreach (self::byType('meteopollen', true) as $eq) {
      if ($type && $eq->getConfiguration('type') != $type) continue;
      try { $eq->$f(); } catch (Throwable $e) { log::add('meteopollen', 'error', $eq->getHumanName() . ' : ' . $e->getMessage()); }
    }
  }

  public static function cron5() { self::chaque('meteo', 'pluieHeure'); self::chaque('meteo', 'copier'); }
  public static function cronHourly() { self::chaque('', 'maj'); }

  // commandes propres : logicalId => [nom, sous-type, unité, historisée]
  private function commandes() {
    $l = [];
    if ($this->getConfiguration('type') == 'pollen') {
      foreach (self::POLLENS as $k => $nom) $l[$k] = [__($nom, __FILE__), 'numeric', 'g/m³', 1];
    } elseif ($this->getConfiguration('type') == 'meteo') {
      $l['PluieJourPct'] = [__('Pluie aujourd\'hui', __FILE__), 'numeric', '%', 1];
      $l['PluieDemainPct'] = [__('Pluie demain', __FILE__), 'numeric', '%', 1];
      $l['om_debut'] = [__('Pluie dans l\'heure, début', __FILE__), 'string', '', 0];
      for ($i = 1; $i <= 5; $i++) $l['om_' . $i] = [__('Pluie dans l\'heure', __FILE__) . ' ' . $i, 'numeric', '', 0];
    }
    return $l;
  }

  public function postSave() {
    $l = $this->commandes();
    foreach ($l as $k => [$nom, $sub, $unite, $hist]) {
      if (is_object($this->getCmd('info', $k))) continue;
      $c = new meteopollenCmd();
      $c->setEqLogic_id($this->getId()); $c->setLogicalId($k); $c->setName($nom); $c->setType('info'); $c->setSubType($sub);
      $c->setUnite($unite); $c->setIsHistorized($hist); $c->setIsVisible(0); $c->save();
    }
    $this->copier();
    if ($l && !is_object($this->getCmd('action', 'refresh'))) {
      $c = new meteopollenCmd();
      $c->setEqLogic_id($this->getId()); $c->setLogicalId('refresh'); $c->setName(__('Rafraîchir', __FILE__));
      $c->setType('action'); $c->setSubType('other'); $c->save();
    }
  }

  public function maj() {
    if ($this->getConfiguration('type') == 'pollen') $this->pollen();
    if ($this->getConfiguration('type') == 'meteo') $this->pluieJour(); // pluie dans l'heure : cron5
  }

  private function source() {
    $t = $this->getConfiguration('type');
    $eq = isset(self::SOURCE[$t]) ? eqLogic::byId($this->getConfiguration('source')) : null;
    return is_object($eq) && $eq->getEqType_name() == self::SOURCE[$t] ? $eq : null;
  }

  private function position() { // celle de l'équipement Météo-France, sinon celle de Jeedom (Réglages › Système › Configuration › Informations)
    $s = $this->source();
    if ($s && $s->getConfiguration('lat') !== '') return [$s->getConfiguration('lat'), $s->getConfiguration('lon')];
    return [config::byKey('info::latitude'), config::byKey('info::longitude')];
  }

  /* Défaut du plugin Météo-France : à chaque sauvegarde de son équipement (preSave → getInsee), il refait la recherche de la
     commune sur api-adresse.data.gouv.fr ; si ce service est lent ou en panne, il efface lat / lon / insee sans prévenir.
     D'où une copie consultable (option « copie » de la tuile Météo), pour pouvoir la retrouver. Rien n'est remis automatiquement. */
  public static function localisation(array $cfg) { // localisation complète de la configuration d'un équipement Météo-France, sinon null
    if (($cfg['lat'] ?? '') === '' || ($cfg['lon'] ?? '') === '') return null;
    $loc = [];
    foreach (self::LOC as $k) $loc[$k] = $cfg[$k] ?? '';
    return $loc;
  }

  public function copier() { // localisation vidée : la dernière copie reste
    $s = $this->source();
    if (!$s || !$this->getConfiguration('copie', 0)) return;
    $loc = self::localisation($s->getConfiguration());
    $c = $this->getConfiguration('loc', []);
    if (!$loc || (is_array($c) && ($c['eq'] ?? '') == $s->getId() && ($c['loc'] ?? null) == $loc)) return;
    $this->setConfiguration('loc', ['eq' => $s->getId(), 'nom' => $s->getHumanName(), 'date' => date('Y-m-d H:i'), 'loc' => $loc]);
    $this->save(true); // sans hooks : appelé aussi depuis postSave
  }

  public static function avertir() { // à l'installation : seulement s'il y a des équipements Météo-France
    try { $mf = eqLogic::byType('meteofrance'); } catch (Throwable $e) { return false; }
    if (!$mf) return false;
    foreach (self::byType('meteopollen') as $eq) if ($eq->getConfiguration('type') == 'meteo' && $eq->getConfiguration('copie', 0)) return false;
    message::add('meteopollen', __('Plugin Météo-France : à chaque sauvegarde d\'un de ses équipements, il refait la recherche de la commune sur api-adresse.data.gouv.fr ; si ce service ne répond pas, il efface la latitude, la longitude et le code INSEE, et sa tuile affiche « Erreur de configuration ». Météo & Pollen peut en garder une copie consultable : cocher « Copie de la localisation » dans votre tuile Météo.', __FILE__));
    return true;
  }

  private static function json($url) {
    try { return json_decode((new com_http($url))->exec(10, 1), true); } catch (Throwable $e) { log::add('meteopollen', 'warning', $url . ' : ' . $e->getMessage()); return null; }
  }

  private function info($k, $v) {
    $c = $this->getCmd('info', $k);
    if (is_object($c)) $this->checkAndUpdateCmd($c, $v);
  }

  // pollens du reste de la journée : max horaire en grains/m³
  private function pollen() {
    [$lat, $lon] = $this->position();
    if ($lat === '' || $lon === '') return;
    $r = self::json('https://air-quality-api.open-meteo.com/v1/air-quality?latitude=' . urlencode($lat) . '&longitude=' . urlencode($lon)
      . '&hourly=' . implode(',', array_map(function ($k) { return $k . '_pollen'; }, array_keys(self::POLLENS))) . '&forecast_days=1&timezone=auto');
    if (empty($r['hourly']['time'])) return;
    $h = date('Y-m-d\TH:00');
    foreach (self::POLLENS as $k => $nom) {
      $m = 0;
      foreach ($r['hourly']['time'] as $i => $t) if ($t >= $h) $m = max($m, (float)($r['hourly'][$k . '_pollen'][$i] ?? 0));
      $this->info($k, round($m, 1));
    }
  }

  // risque de pluie (max %) sur le reste de la journée et demain : même service que le plugin Météo-France, qui ne garde que le 1er créneau de 3 h
  private function pluieJour() {
    $s = $this->source();
    if (!$s || !class_exists('meteofrance')) return;
    $r = meteofrance::callMeteoWS('https://webservice.meteofrance.com/forecast?lat=' . urlencode($s->getConfiguration('lat')) . '&lon=' . urlencode($s->getConfiguration('lon')) . '&id=&instants=', false, true);
    if (!is_array($r) || empty($r['probability_forecast'])) return;
    $fin = strtotime('tomorrow'); $max = ['jour' => 0, 'demain' => 0];
    foreach ($r['probability_forecast'] as $p) {
      $v = $p['rain']['3h'] ?? $p['rain']['6h'] ?? null; // créneaux de 3 h, puis 6 h plus loin
      if ($v === null || $p['dt'] + 3 * 3600 <= time()) continue;
      $k = $p['dt'] < $fin ? 'jour' : ($p['dt'] < $fin + 86400 ? 'demain' : null);
      if ($k) $max[$k] = max($max[$k], $v);
    }
    $this->info('PluieJourPct', $max['jour']);
    $this->info('PluieDemainPct', $max['demain']);
  }

  public static function niveau($mmh) { return $mmh < 0.1 ? 1 : ($mmh < 2.5 ? 2 : ($mmh < 7.6 ? 3 : 4)); } // seuils usuels (mm/h), échelle Météo-France 1 sec → 4 forte

  // pluie dans l'heure d'Open-Meteo : 5 quarts d'heure (2e barre de la tuile, secours quand Météo-France ne répond pas)
  public function pluieHeure() {
    [$lat, $lon] = $this->position();
    if ($lat === '' || $lon === '') return;
    $r = self::json('https://api.open-meteo.com/v1/forecast?latitude=' . urlencode($lat) . '&longitude=' . urlencode($lon)
      . '&minutely_15=precipitation&past_minutely_15=1&forecast_minutely_15=5&timezone=auto');
    $om = [];
    foreach ($r['minutely_15']['time'] ?? [] as $i => $t) {
      $d = strtotime($t);
      if ($d + 900 > time() && count($om) < 5) $om[] = [$d, self::niveau(4 * (float)$r['minutely_15']['precipitation'][$i])];
    }
    $this->info('om_debut', $om ? date('Hi', $om[0][0]) : ''); // '' = indisponible
    for ($i = 0; $i < 5; $i++) $this->info('om_' . ($i + 1), $om[$i][1] ?? 1);
  }

  private static function lib() { // ponytail: bibliothèque recopiée dans chaque tuile (gardée par window.meteopollen) ; fichier servi à part si le poids gêne
    static $js = [];
    $l = translate::getLanguage();
    return $js[$l] ??= translate::exec(file_get_contents(__DIR__ . '/../template/meteopollen.js'), 'plugins/meteopollen/core/template/meteopollen.js');
  }

  // données de la tuile : commandes de l'équipement lu et des siennes, réglages
  public function donnees($_user = null) {
    $k = []; $c = [];
    $src = $this->source();
    if ($src && !$src->hasRight('r', $_user)) $src = null;
    foreach (array_filter([$src, $this]) as $eq) {
      foreach ($eq->getCmd() as $cmd) {
        $id = $cmd->getId(); $lid = $cmd->getLogicalId();
        $c[$id] = ['id' => $id, 'n' => $cmd->getName(), 'u' => $cmd->getUnite(), 'v' => $cmd->getType() == 'info' ? $cmd->execCmd() : null];
        if ($lid !== '' && !isset($k[$lid])) $k[$lid] = $id;
        if (!isset($k[$cmd->getName()])) $k[$cmd->getName()] = $id;
      }
    }
    $cf = ['sources' => $this->getConfiguration('sources', '') ?: 'MF'];
    return ['t' => $this->getConfiguration('type'), 'id' => $this->getId(), 'k' => $k, 'c' => $c, 'cf' => $cf];
  }

  public function toHtml($_version = 'dashboard') {
    $replace = $this->preToHtml($_version, [], true); // sans cache : la tuile embarque les valeurs de l'équipement lu
    if (!is_array($replace)) return $replace;
    $d = $this->donnees();
    $json = json_encode($d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR); // inséré après template_replace : les # ne sont pas touchés
    $replace['#cmd#'] = '';
    $replace += ['#eqLogic_class#' => 'eqLogic_layout_default', '#background-color#' => ''];
    $html = template_replace($replace, getTemplate('core', 'dashboard', 'tuile', 'meteopollen')); // ponytail: même gabarit en mobile
    return $this->postToHtml($_version, strtr($html, ['@@LIB@@' => self::lib(), '@@DATA@@' => $json]));
  }
}

class meteopollenCmd extends cmd {
  public function execute($_options = []) {
    if ($this->getLogicalId() != 'refresh') return;
    $eq = $this->getEqLogic();
    $eq->maj();
    if ($eq->getConfiguration('type') == 'meteo') $eq->pluieHeure();
  }
}
