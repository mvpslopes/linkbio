(function () {
  "use strict";

  function initReveal() {
    var els = document.querySelectorAll(".fade-in");
    if (!("IntersectionObserver" in window)) {
      els.forEach(function (el) {
        el.classList.add("visible");
      });
      return;
    }
    var obs = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            e.target.classList.add("visible");
            obs.unobserve(e.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
    );
    els.forEach(function (el) {
      obs.observe(el);
    });
  }

  function initSmoothAnchors() {
    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
      link.addEventListener("click", function (e) {
        var id = link.getAttribute("href");
        if (!id || id === "#") return;
        var target = document.querySelector(id);
        if (!target) return;
        e.preventDefault();
        var y = target.getBoundingClientRect().top + window.scrollY - 72;
        window.scrollTo({ top: y, behavior: "smooth" });
      });
    });
  }

  function initHeaderShadow() {
    var header = document.querySelector(".site-header");
    if (!header) return;
    function onScroll() {
      if (window.scrollY > 12) {
        header.style.boxShadow = "0 8px 32px rgba(0,0,0,0.35)";
      } else {
        header.style.boxShadow = "none";
      }
    }
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  function initSplash() {
    var splash = document.getElementById("splash");
    if (!splash) return;
    window.addEventListener("load", function () {
      setTimeout(function () {
        splash.classList.add("splash--hide");
      }, 2100);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initSplash();
    initReveal();
    initSmoothAnchors();
    initHeaderShadow();
  });
})();
