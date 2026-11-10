/* Météo & Pollen — rendu des tuiles pollen et meteo. Recopié dans chaque tuile par meteopollen::toHtml(),
   chargé une seule fois par page. Textes entre doubles accolades traduits côté PHP (core/i18n). */
if (!window.meteopollen) (function () {
  var css = document.createElement('style')
  css.textContent =
    '.tu{box-sizing:border-box;width:100%;display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 8px 12px;text-align:center;min-width:174px}' +
    '.tu-ic{font-size:28px;opacity:.55;margin:4px 0 2px}.tu-ok .tu-ic{opacity:1;color:#e8a23a}' +
    '.tu-big{font-size:22px;font-weight:300}' +
    '.tu-l{font-size:12px;opacity:.75;white-space:pre-wrap}.tu-l:empty,.tu-al:empty{display:none}' +
    '.tu-al{font-size:11px;font-weight:600;color:#fff;background:#b3261e;border-radius:9px;padding:1px 9px;margin-top:2px}' +
    '.tu-jg{display:grid;grid-template-columns:auto 1fr auto;gap:3px 6px;align-items:center;width:160px;font-size:11px;margin-top:4px}' +
    '.tu-jg span,.tu-jg em{opacity:.8}.tu-jg i{height:6px;background:rgba(128,128,128,.3);border-radius:3px;overflow:hidden}' +
    '.tu-jg b{display:block;height:100%}.tu-jg em{font-style:normal;text-align:right}' +
    '.tu-bt{cursor:pointer;border-radius:12px;padding:2px 10px;font-size:12px;background:rgba(128,128,128,.2);border:0;color:inherit}'
  document.head.appendChild(css)
  function esc(s) { return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (x) { return '&#' + x.charCodeAt(0) + ';' }) }
  function f1(x) { return x === undefined || x === '' || x === null ? '–' : (+x).toFixed(1).replace('.', ',') }
  function barre(nom, p, t, coul) { return '<span>' + esc(nom) + '</span><i><b style="width:' + Math.max(0, Math.min(100, p)) + '%;background:' + coul + '"></b></i><em>' + esc(t) + '</em>' }

  var R = {}

  R.pollen = {ic: 'seedling', rendu: function (b, a) {
    // [nom, logicalId, seuils faible / moyen / élevé / très élevé en grains/m³] ; seuils approchés (le RNSA publie des niveaux, pas des grains/m³)
    var P = [["{{Aulne}}", 'alder', [1, 10, 50, 200]], ["{{Bouleau}}", 'birch', [1, 10, 50, 200]], ["{{Olivier}}", 'olive', [1, 10, 50, 200]],
      ["{{Graminées}}", 'grass', [1, 10, 30, 80]], ["{{Armoise}}", 'mugwort', [1, 5, 15, 40]], ["{{Ambroisie}}", 'ragweed', [1, 3, 10, 30]]]
    var NIV = ["{{nul}}", "{{faible}}", "{{moyen}}", "{{élevé}}", "{{très élevé}}"], NIVT = ['', '', "{{Pollens moyens}}", "{{Pollens élevés}}", "{{Pollens très élevés}}"]
    var NIVC = ['#888', '#6c6', '#e8c33a', '#e8862a', '#e0443a']
    b.insertAdjacentHTML('beforeend', '<div class="tu-jg"></div>')
    function maj() {
      var p = P.map(function (x) { var g = +a.v(x[1]) || 0; return {n: x[0], g: g, l: x[2].filter(function (s) { return g >= s }).length} })
        .sort(function (x, y) { return y.l - x.l || y.g - x.g })
      var m = p[0].l
      b.classList.toggle('tu-ok', m > 1)
      a.txt('.tu-big', !m ? "{{Pas de pollen}}" : m == 1 ? "{{Pollens faibles}}" : NIVT[m])
      b.querySelector('.tu-big').style.color = m ? NIVC[m] : ''
      a.txt('.tu-l', m ? "{{surtout}} " + p[0].n.toLowerCase() : "{{aujourd'hui}}")
      b.querySelector('.tu-jg').innerHTML = p.map(function (x) { return barre(x.n, x.l * 25, NIV[x.l], NIVC[x.l]) }).join('')
    }
    a.s(P.map(function (x) { return x[1] }), maj)
  }}

  R.meteo = {ic: 'sun', rendu: function (b, a) {
    var MF = ['Rainrain1', 'Rainrain2', 'Rainrain3', 'Rainrain4', 'Rainrain5', 'Rainrain6', 'Rainrain7', 'Rainrain8', 'Rainrain9'] // 6 × 5 min puis 3 × 10 min
    var OM = ['om_1', 'om_2', 'om_3', 'om_4', 'om_5'] // 5 × 15 min
    var COUL = ['rgba(128,128,128,.25)', 'rgba(128,128,128,.25)', '#8ab8ff', '#3d7fe0', '#1a4fb0'], NIVP = ['', "{{Temps sec}}", "{{Pluie faible}}", "{{Pluie modérée}}", "{{Pluie forte}}"]
    var ordre = a.cf.sources == 'OM' ? ['OM', 'MF'] : ['MF', 'OM']
    function ciel(d) { d = d.toLowerCase(); return /orage/.test(d) ? 'bolt' : /neige/.test(d) ? 'snowflake' : /pluie|averse|bruine/.test(d) ? 'cloud-rain' : /brouillard|brume/.test(d) ? 'smog' : /éclaircie|voil/.test(d) ? 'cloud-sun' : /couvert|nuag/.test(d) ? 'cloud' : 'sun' }
    function hm(m) { m = (m + 1440) % 1440; return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0') }
    function r(x) { return x === undefined || x === null || x === '' ? '–' : Math.round(x) }
    // pluie dans l'heure : 1re source disponible dans l'ordre choisi, niveaux 1 sec → 4 forte
    function pluieH() {
      var n = new Date(), now = n.getHours() * 60 + n.getMinutes()
      function src(nom, h, durs, ks) { // tranches [début relatif à maintenant (min), fin, niveau] ; null si indisponible
        if (h === undefined || h === null || h === '') return null
        h = String(h).padStart(4, '0')
        var t = (+h.slice(0, 2) * 60 + +h.slice(2) - now + 2160) % 1440 - 720
        return {nom: nom, s: ks.map(function (k, i) { var d = t; t += durs[i]; return [d, t, +a.v(k) || 1] })}
      }
      // Météo-France : en échec, le plugin remet l'heure mais laisse les tranches figées ; cumul Raincumul = 0
      var S = [a.v('Raincumul') == 0 ? null : src('MF', a.v('Rainheure'), [5, 5, 5, 5, 5, 5, 10, 10, 10], MF), src('OM', a.v('om_debut'), [15, 15, 15, 15, 15], OM)]
      var p = S.filter(Boolean).sort(function (x, y) { return ordre.indexOf(x.nom) - ordre.indexOf(y.nom) })[0]
      var q = p && p.s.find(function (x) { return x[2] > 1 && x[1] > 0 && x[0] < 60 }), dans = q ? Math.max(0, Math.round(q[0] / 5) * 5) : 0
      function ligne(x, nom) {
        var seg = x ? x.s.map(function (s) { var d = Math.max(0, s[0]), f = Math.min(60, s[1]); return f > d ? '<i style="flex:' + (f - d) + ';border-radius:2px;background:' + COUL[s[2]] + '" title="' + nom + ' ' + hm(now + s[0]) + ' : ' + NIVP[s[2]] + '"></i>' : '' }).join('')
          : '<i style="flex:1;border-radius:2px;background:' + COUL[0] + '" title="' + nom + ' ' + "{{indisponible}}" + '"></i>'
        return '<div style="display:flex;gap:2px;height:8px;margin-top:3px"><span style="flex:none;width:18px;font-size:8px;line-height:8px;opacity:.6;text-align:left">' + nom + '</span>' + seg + '</div>'
      }
      return {txt: !p ? "{{Pluie dans l'heure indisponible}}" : !q ? "{{Pas de pluie dans l'heure}}" : dans == 0 ? "{{Pluie en ce moment}}" : "{{Pluie dans}} " + dans + ' min',
        html: ordre.map(function (n) { return n == 'OM' ? ligne(S[1], 'OM') : a.c('Rainheure') ? ligne(S[0], 'MF') : '' }).join('') + '<div style="display:flex;justify-content:space-between;font-size:9px;opacity:.6;padding-left:20px"><span>' + hm(now) + '</span><span>+30 min</span><span>' + hm(now + 60) + '</span></div>'}
    }
    b.querySelector('.tu-al').insertAdjacentHTML('beforebegin', '<div class="tu-ph" style="width:100%;max-width:260px;margin:2px 0 4px"></div><span class="tu-l tu-l3"></span><span class="tu-l tu-l4"></span><span class="tu-l tu-l5"></span>')
    function maj() {
      var d = String(a.v('Meteoday0description') || ''), p = pluieH()
      b.querySelector('.tu-ic').className = 'tu-ic fas fa-' + ciel(d)
      a.txt('.tu-big', f1(a.v('MeteonowTemperature')) + ' °C')
      a.txt('.tu-l', "{{Aujourd'hui}} : " + d + ' · ' + r(a.v('Meteoday0temperatureMin')) + '° / ' + r(a.v('Meteoday0temperatureMax')) + '° · ' + "{{ressenti}} " + r(a.v('MeteonowTemperatureRes')) + '°')
      a.txt('.tu-l2', p.txt)
      b.querySelector('.tu-ph').innerHTML = p.html
      a.txt('.tu-l3', "{{Risque de pluie}} : " + r(a.v('MeteoprobaPluie')) + ' % (3 h) · ' + r(a.v('PluieJourPct')) + ' % ' + "{{aujourd'hui}}" + ' · ' + r(a.v('PluieDemainPct')) + ' % ' + "{{demain}}")
      a.txt('.tu-l4', "{{Humidité}} " + r(a.v('MeteonowHumidity')) + ' % · ' + "{{vent}} " + r(a.v('MeteonowWindSpeed')) + ' km/h' + (+a.v('MeteonowWindGust') ? ' (' + "{{rafales}} " + a.v('MeteonowWindGust') + ')' : '') + ' · UV ' + r(a.v('Meteoday0indiceUV')))
      a.txt('.tu-l5', "{{Demain}} : " + (a.v('Meteoday1description') || '') + ' · ' + r(a.v('Meteoday1temperatureMin')) + '° / ' + r(a.v('Meteoday1temperatureMax')) + '°')
      var vg = +a.v('Vigilancecolor_max') || 0
      a.txt('.tu-al', vg > 1 ? ['', '', "{{Vigilance jaune}}", "{{Vigilance orange}}", "{{Vigilance rouge}}"][vg] || '' : '')
    }
    a.s(MF.concat(OM, ['Rainheure', 'Raincumul', 'om_debut', 'Meteoday0description', 'MeteonowTemperature', 'Meteoday0temperatureMin', 'Meteoday0temperatureMax',
      'MeteonowTemperatureRes', 'MeteoprobaPluie', 'PluieJourPct', 'PluieDemainPct', 'MeteonowHumidity', 'MeteonowWindSpeed', 'MeteonowWindGust', 'Meteoday0indiceUV',
      'Meteoday1description', 'Meteoday1temperatureMin', 'Meteoday1temperatureMax', 'Vigilancecolor_max']), maj)
    var t = setInterval(function () { if (b.isConnected) maj(); else clearInterval(t) }, 60000) // la barre glisse avec l'heure
  }}

  var SUBS = {} // id de commande → [{b, f}] ; jeedom.cmd.addUpdateFunction écarte une fonction au même texte qu'une déjà inscrite
  function suivre(id, b, f) {
    if (!SUBS[id]) {
      SUBS[id] = []
      jeedom.cmd.addUpdateFunction(id, function (o) {
        SUBS[id] = SUBS[id].filter(function (s) { return s.b.isConnected })
        SUBS[id].forEach(function (s) { s.f(o.value) })
      })
    }
    SUBS[id].push({b: b, f: f})
  }

  window.meteopollen = {init: function (b, d) {
    var r = R[d.t]
    if (!b || !r) { if (b) b.textContent = "{{Choisissez le type de la tuile dans sa configuration.}}"; return }
    b.innerHTML = '<i class="tu-ic fas fa-' + r.ic + '"></i><b class="tu-big"></b><span class="tu-l"></span><span class="tu-l tu-l2"></span><span class="tu-al"></span>'
    function c(k) { return d.k[k] ? d.c[d.k[k]] : undefined }
    r.rendu(b, {
      id: d.id, cf: d.cf,
      c: c,                                                      // commande : {id, n: nom, u: unité, v: valeur}
      v: function (k) { var x = c(k); return x ? x.v : undefined },
      s: function (ks, fn) { // suit les commandes ks en direct
        ks.forEach(function (k) {
          var x = c(k)
          if (x) suivre(x.id, b, function (v) { x.v = v; fn() })
        })
        fn()
      },
      txt: function (sel, t) { var e = b.querySelector(sel); if (e) e.textContent = t }
    })
  }}
})()
