(function () {
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // angka di dashboard "menghitung naik"
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var end = parseFloat(el.getAttribute('data-count')) || 0;
    var pre = el.getAttribute('data-prefix') || '';
    if (reduced || end <= 0) return;
    var t0 = null, dur = 900;
    function step(ts) {
      if (t0 === null) t0 = ts;
      var p = Math.min((ts - t0) / dur, 1);
      var v = Math.round(end * (1 - Math.pow(1 - p, 3)));
      el.textContent = pre + v.toLocaleString('id-ID');
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  });

  // percikan bintang kecil saat tombol diklik
  document.addEventListener('click', function (e) {
    if (reduced) return;
    var b = e.target.closest ? e.target.closest('.btn') : null;
    if (!b) return;
    var icons = ['✦', '★', '♥'];
    for (var i = 0; i < 6; i++) {
      var s = document.createElement('span');
      s.className = 'spark';
      s.textContent = icons[i % 3];
      s.style.left = e.clientX + 'px';
      s.style.top = e.clientY + 'px';
      s.style.setProperty('--dx', (Math.random() * 90 - 45) + 'px');
      s.style.setProperty('--dy', (-30 - Math.random() * 55) + 'px');
      document.body.appendChild(s);
      (function (n) { setTimeout(function () { n.remove(); }, 900); })(s);
    }
  });
})();
