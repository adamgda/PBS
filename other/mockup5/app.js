/* PBS — shared navigation, icons (inline SVG) & interactions. No emojis. */
(function () {
  "use strict";

  // ---- Icon set (outline, 24x24, stroke based) ----
  var I = {
    dashboard: '<path d="M4 4h7v7H4z"/><path d="M13 4h7v4h-7z"/><path d="M13 12h7v8h-7z"/><path d="M4 15h7v5H4z"/>',
    workers: '<path d="M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/><path d="M2.5 20v-1.2a4.8 4.8 0 0 1 9.6 0V20"/><path d="M16 11.2a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M15 20v-1.1a4.6 4.6 0 0 1 6.5-4.2"/>',
    gear: '<path d="M3 7h11"/><path d="M18 7h3"/><path d="M15.5 7a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/><path d="M3 17h3"/><path d="M10 17h11"/><path d="M8.5 17a2.5 2.5 0 1 0-5 0 2.5 2.5 0 0 0 5 0z"/>',
    terminal: '<path d="M3 21h18"/><path d="M5 21V8l7-4 7 4v13"/><path d="M9 21v-5h6v5"/><path d="M9 11h6"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="M8 13h3v3H8z"/>',
    chart: '<path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-4"/><path d="M12 16V9"/><path d="M16 16v-6"/><path d="M20 16v-2"/>',
    report: '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 12h6"/><path d="M10 16h6"/><path d="M10 8h2"/>',
    settings: '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/><path d="M19.4 13.5a1.7 1.7 0 0 0 .33 1.86l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.86-.33 1.7 1.7 0 0 0-1 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.11-1.56 1.7 1.7 0 0 0-1.86.33l-.06.06A2 2 0 1 1 4.18 14.9l.06-.06a1.7 1.7 0 0 0 .33-1.86 1.7 1.7 0 0 0-1.56-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.56-1.11 1.7 1.7 0 0 0-.33-1.86l-.06-.06A2 2 0 1 1 7.1 1.18l.06.06a1.7 1.7 0 0 0 1.86.33H9a1.7 1.7 0 0 0 1-1.56V0a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.56 1.7 1.7 0 0 0 1.86-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.33 1.86V7a1.7 1.7 0 0 0 1.56 1H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1z"/>',
    alert: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    bell: '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9z"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
    plus: '<path d="M12 5v14"/><path d="M5 12h14"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    filter: '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
    menu: '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
    close: '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
    chevronL: '<path d="M15 6l-6 6 6 6"/>',
    chevronR: '<path d="M9 6l6 6-6 6"/>',
    chevronD: '<path d="M6 9l6 6 6-6"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    trash: '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/>',
    doc: '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h6"/>',
    truck: '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/>',
    anchor: '<circle cx="12" cy="5" r="2.2"/><path d="M12 7v14"/><path d="M5 13a7 7 0 0 0 14 0"/><path d="M5 13H3"/><path d="M21 13h-2"/>',
    clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    pin: '<path d="M12 21s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
    phone: '<path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 5 5L20 13l2 5v3a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/>',
    mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    eye: '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    check: '<path d="M5 12l5 5L20 6"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 12 0v1"/>',
    logout: '<path d="M9 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
    download: '<path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>',
    wrench: '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.8 2.8-2.5-2.5z"/>',
    shield: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
    history: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 4v4h4"/><path d="M12 8v4l3 2"/>',
    refresh: '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 4v4h-4"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 20v-4h4"/>',
    copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
    send: '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/>',
    layers: '<path d="M12 3 2 8l10 5 10-5z"/><path d="M2 13l10 5 10-5"/><path d="M2 18l10 5 10-5"/>',
    arrowUp: '<path d="M12 19V5"/><path d="M6 11l6-6 6 6"/>',
    dot: '<circle cx="12" cy="12" r="4"/>',
    flag: '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>',
    money: '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v.01M18 15v.01"/>',
  };

  function svg(name, cls) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="' + (cls || "w-6 h-6") + '">' + (I[name] || "") + "</svg>";
  }

  var NAV = [
    { key: "dashboard", label: "Dashboard", href: "index.html", icon: "dashboard" },
    { key: "pracownicy", label: "Pracownicy", href: "pracownicy.html", icon: "workers" },
    { key: "sprzet", label: "Sprzęt", href: "sprzet.html", icon: "gear" },
    { key: "terminale", label: "Terminale", href: "terminale.html", icon: "terminal" },
    { key: "harmonogram", label: "Harmonogram", href: "harmonogram.html", icon: "calendar" },
    { key: "analityka", label: "Analityka", href: "analityka.html", icon: "chart" },
    { key: "raportowanie", label: "Raportowanie", href: "raportowanie.html", icon: "report" },
    { key: "ustawienia", label: "Ustawienia", href: "ustawienia.html", icon: "settings" },
    { key: "awaria", label: "Awaria!", href: "awaria.html", icon: "alert", danger: true },
  ];

  function itemByKey(k) { for (var i = 0; i < NAV.length; i++) if (NAV[i].key === k) return NAV[i]; return NAV[0]; }

  // ---- Sidebar (desktop >=lg) ----
  function sidebar(active) {
    var h = "";
    for (var i = 0; i < NAV.length; i++) {
      var n = NAV[i];
      var on = n.key === active;
      var danger = n.danger ? "text-rose-300" : "text-slate-300";
      h += '<a href="' + n.href + '" class="pbs-side-item flex items-center gap-3 px-3 py-2.5 rounded-xl ' + (on ? "pbs-side-active " + danger : danger) + " hover:bg-white/5 transition" + '">' +
        '<span class="pbs-side-ico ' + (on ? "text-cyan-300" : n.danger ? "text-rose-400" : "text-slate-400") + '">' + svg(n.icon, "w-5 h-5") + "</span>" +
        '<span class="text-sm font-medium">' + n.label + "</span>" +
        (n.danger ? '<span class="ml-auto text-[10px] font-bold tracking-wider px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300">SOS</span>' : "") +
        "</a>";
    }
    return h;
  }

  // ---- Drawer (mobile) ----
  function drawer(active) {
    return sidebar(active);
  }

  // ---- Bottom nav (mobile) ----
  var QUICK = ["dashboard", "harmonogram", "awaria", "menu"];
  function bottomNav(active) {
    var h = "";
    for (var i = 0; i < QUICK.length; i++) {
      var key = QUICK[i];
      if (key === "menu") {
        h += '<button id="pbs-menu-btn" class="flex flex-col items-center justify-center gap-1 flex-1 py-1.5 text-slate-500">' +
          '<span>' + svg("menu", "w-6 h-6") + '</span><span class="text-[10px] font-medium">Więcej</span></button>';
        continue;
      }
      var n = itemByKey(key);
      var on = n.key === active;
      var color = on ? (n.danger ? "text-rose-600" : "text-cyan-600") : (n.danger ? "text-rose-500" : "text-slate-500");
      h += '<a href="' + n.href + '" class="flex flex-col items-center justify-center gap-1 flex-1 py-1.5 ' + color + ' relative">' +
        '<span>' + svg(n.icon, "w-6 h-6") + "</span>" +
        (on ? '<span class="absolute top-0 h-1 w-6 rounded-full ' + (n.danger ? "bg-rose-500" : "bg-cyan-500") + '"></span>' : "") +
        '<span class="text-[10px] font-medium">' + n.label + "</span></a>";
    }
    return h;
  }

  // ---- Header ----
  function header(active) {
    var n = itemByKey(active);
    return '<div class="flex items-center gap-3">' +
      '<button id="pbs-burger" class="lg:hidden -ml-2 p-2 rounded-lg text-slate-600 hover:bg-slate-100">' + svg("menu", "w-6 h-6") + "</button>" +
      '<div class="lg:hidden flex items-center gap-2">' +
      '<span class="grid place-items-center w-9 h-9 rounded-xl bg-brand-900 text-cyan-300 font-black">PB</span></div>' +
      '<h1 class="text-lg sm:text-xl font-bold text-slate-800 truncate">' + n.label + "</h1></div>" +
      '<div class="flex items-center gap-2">' +
      '<a href="awaria.html" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-sm font-semibold border border-rose-200 hover:bg-rose-100">' + svg("alert", "w-4 h-4") + "Zgłoś awarię</a>" +
      '<button class="relative p-2 rounded-lg text-slate-500 hover:bg-slate-100">' + svg("bell", "w-6 h-6") + '<span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-500 ring-2 ring-white"></span></button>' +
      '<div class="w-9 h-9 rounded-full bg-brand-900 text-white grid place-items-center font-semibold text-sm">JK</div></div>';
  }

  function fill(elId, html) { var el = document.getElementById(elId); if (el) el.innerHTML = html; }

  var PBS = {
    init: function (active) {
      fill("pbs-sidebar", sidebar(active));
      fill("pbs-drawer", drawer(active));
      fill("pbs-bottomnav", bottomNav(active));
      fill("pbs-header", header(active));

      var burger = document.getElementById("pbs-burger");
      var menuBtn = document.getElementById("pbs-menu-btn");
      var drawerEl = document.getElementById("pbs-drawer");
      var overlay = document.getElementById("pbs-overlay");
      var closeBtn = document.getElementById("pbs-drawer-close");

      function openDrawer() { drawerEl.classList.add("open"); overlay.classList.add("open"); document.body.style.overflow = "hidden"; }
      function closeDrawer() { drawerEl.classList.remove("open"); overlay.classList.remove("open"); document.body.style.overflow = ""; }

      if (burger) burger.addEventListener("click", openDrawer);
      if (menuBtn) menuBtn.addEventListener("click", openDrawer);
      if (overlay) overlay.addEventListener("click", closeDrawer);
      if (closeBtn) closeBtn.addEventListener("click", closeDrawer);

      // generic filter tag toggle
      document.querySelectorAll("[data-filter-tag]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var group = btn.getAttribute("data-filter-group");
          document.querySelectorAll('[data-filter-group="' + group + '"]').forEach(function (b) { b.classList.remove("active"); });
          btn.classList.add("active");
        });
      });

      // ---- Modals ----
      function openModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.classList.remove("hidden");
        m.classList.add("flex");
        document.body.style.overflow = "hidden";
        // focus first input if any
        var firstInput = m.querySelector("input,textarea,select");
        if (firstInput) setTimeout(function(){ firstInput.focus(); }, 50);
      }
      function closeModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.classList.add("hidden");
        m.classList.remove("flex");
        document.body.style.overflow = "";
      }
      window.pbsOpenModal = openModal;
      window.pbsCloseModal = closeModal;

      // wire up [data-modal] triggers
      document.querySelectorAll("[data-modal]").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          openModal(btn.getAttribute("data-modal"));
        });
      });
      // wire up [data-modal-close] closers and backdrop click
      document.querySelectorAll(".pbs-modal").forEach(function (m) {
        m.addEventListener("click", function (e) {
          if (e.target === m) closeModal(m.id);
        });
      });
      document.querySelectorAll("[data-modal-close]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var modal = btn.closest(".pbs-modal");
          if (modal) closeModal(modal.id);
        });
      });
      // Esc closes topmost open modal
      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
          var open = document.querySelector(".pbs-modal.flex");
          if (open) closeModal(open.id);
        }
      });
    }
  };
  window.PBS = PBS;
  window.pbsIcon = svg;
})();