(function () {
  "use strict";

  var API_URL = "api/chat.php";
  var history = [];
  var busy = false;

  var SUGGESTIONS_START = [
    "O que é o MFIT?",
    "Como funciona?",
    "Quero treinar online",
    "Falar no WhatsApp",
  ];

  var SUGGESTIONS_NEXT = [
    "Quanto custa?",
    "O que está incluso?",
    "Quero começar",
    "Continuar no WhatsApp",
  ];

  function el(tag, className, html) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (html != null) node.innerHTML = html;
    return node;
  }

  function buildUI() {
    var root = el("div", "jp-agent");
    root.innerHTML =
      '<button type="button" class="jp-agent-fab" aria-label="Abrir assistente de IA" aria-expanded="false">' +
      '<svg class="jp-agent-fab-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
      '<path d="M12 2.5l1.15 3.35L16.5 7l-3.35 1.15L12 11.5l-1.15-3.35L7.5 7l3.35-1.15L12 2.5Z" fill="currentColor"/>' +
      '<path d="M18.2 10.2l.72 2.1 2.1.72-2.1.72-.72 2.1-.72-2.1-2.1-.72 2.1-.72.72-2.1Z" fill="currentColor" opacity=".9"/>' +
      '<path d="M6.4 13.4l.55 1.6 1.6.55-1.6.55-.55 1.6-.55-1.6-1.6-.55 1.6-.55.55-1.6Z" fill="currentColor" opacity=".85"/>' +
      '<rect x="7.2" y="14.2" width="9.6" height="6.2" rx="3.1" stroke="currentColor" stroke-width="1.5"/>' +
      '<circle cx="10.1" cy="17.3" r="0.9" fill="currentColor"/>' +
      '<circle cx="13.9" cy="17.3" r="0.9" fill="currentColor"/>' +
      '<path d="M12 14.2V12.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>' +
      "</svg></button>" +
      '<div class="jp-agent-panel" role="dialog" aria-label="Assistente Jéssica Personal" hidden>' +
      '<header class="jp-agent-head">' +
      '<div><strong>Assistente</strong><span>Jéssica Personal</span></div>' +
      '<button type="button" class="jp-agent-close" aria-label="Fechar">&times;</button>' +
      "</header>" +
      '<div class="jp-agent-messages" id="jp-agent-messages"></div>' +
      '<div class="jp-agent-suggestions" id="jp-agent-suggestions" aria-label="Sugestões de resposta"></div>' +
      '<div class="jp-agent-actions">' +
      '<a class="jp-agent-btn jp-agent-btn--wa" id="jp-agent-wa" href="https://wa.me/5531983955337?text=Ol%C3%A1%2C+J%C3%A9ssica%21+Vim+pelo+assistente+do+site." target="_blank" rel="noopener noreferrer">Continuar no WhatsApp</a>' +
      "</div>" +
      '<form class="jp-agent-form" id="jp-agent-form">' +
      '<input type="text" id="jp-agent-input" maxlength="800" placeholder="Digite sua dúvida..." autocomplete="off" required />' +
      '<button type="submit" aria-label="Enviar">➤</button>' +
      "</form></div>";

    document.body.appendChild(root);

    var fab = root.querySelector(".jp-agent-fab");
    var panel = root.querySelector(".jp-agent-panel");
    var closeBtn = root.querySelector(".jp-agent-close");
    var form = root.querySelector("#jp-agent-form");
    var input = root.querySelector("#jp-agent-input");
    var messages = root.querySelector("#jp-agent-messages");

    function openPanel() {
      panel.hidden = false;
      fab.setAttribute("aria-expanded", "true");
      root.classList.add("jp-agent--open");
      if (!messages.dataset.greeted) {
        addBubble(
          "assistant",
          "Oi! Posso te contar sobre o MFIT Personal ou te levar ao WhatsApp para fechar. O que você precisa?"
        );
        renderSuggestions(SUGGESTIONS_START);
        messages.dataset.greeted = "1";
      }
      setTimeout(function () {
        input.focus();
      }, 50);
    }

    function closePanel() {
      panel.hidden = true;
      fab.setAttribute("aria-expanded", "false");
      root.classList.remove("jp-agent--open");
    }

    fab.addEventListener("click", function () {
      if (panel.hidden) openPanel();
      else closePanel();
    });
    closeBtn.addEventListener("click", closePanel);

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var text = (input.value || "").trim();
      if (!text || busy) return;
      input.value = "";
      sendMessage(text, input);
    });

    document.querySelectorAll("[data-open-agent]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        openPanel();
      });
    });

    window.JessicaAgent = { open: openPanel };
  }

  function renderSuggestions(list) {
    var wrap = document.getElementById("jp-agent-suggestions");
    if (!wrap) return;
    wrap.innerHTML = "";
    (list || []).forEach(function (label) {
      var btn = el("button", "jp-agent-chip");
      btn.type = "button";
      btn.textContent = label;
      btn.addEventListener("click", function () {
        if (busy) return;
        var input = document.getElementById("jp-agent-input");
        if (label === "Falar no WhatsApp" || label === "Continuar no WhatsApp") {
          var wa = document.getElementById("jp-agent-wa");
          if (wa) wa.click();
          return;
        }
        sendMessage(label, input);
      });
      wrap.appendChild(btn);
    });
  }

  function pickSuggestions(userText) {
    var t = (userText || "").toLowerCase();
    if (/comprar|pre[cç]o|valor|quanto|pagar|come[cç]ar/.test(t)) {
      return ["Continuar no WhatsApp", "O que está incluso?", "Como funciona?"];
    }
    if (/mfit|app|aplicativo|treino|acesso|funciona|incluso/.test(t)) {
      return ["Quanto custa?", "Quero começar", "Continuar no WhatsApp"];
    }
    return SUGGESTIONS_NEXT;
  }

  function addBubble(role, text) {
    var box = document.getElementById("jp-agent-messages");
    if (!box) return;
    var bubble = el("div", "jp-agent-bubble jp-agent-bubble--" + role);
    bubble.textContent = text;
    box.appendChild(bubble);
    box.scrollTop = box.scrollHeight;
  }

  function addTyping() {
    var box = document.getElementById("jp-agent-messages");
    var tip = el("div", "jp-agent-bubble jp-agent-bubble--assistant jp-agent-typing");
    tip.id = "jp-agent-typing";
    tip.innerHTML = "<span></span><span></span><span></span>";
    box.appendChild(tip);
    box.scrollTop = box.scrollHeight;
  }

  function removeTyping() {
    var tip = document.getElementById("jp-agent-typing");
    if (tip) tip.remove();
  }

  function sendMessage(text, input) {
    if (busy) return;
    busy = true;
    if (input) input.disabled = true;
    renderSuggestions([]);
    addBubble("user", text);
    history.push({ role: "user", content: text });
    addTyping();

    fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: text, history: history.slice(0, -1) }),
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        removeTyping();
        if (!result.ok || !result.data || !result.data.reply) {
          addBubble(
            "assistant",
            (result.data && result.data.error) ||
              "Não consegui responder agora. Fale no WhatsApp."
          );
          renderSuggestions(["Continuar no WhatsApp", "Como funciona?"]);
          return;
        }
        var reply = result.data.reply;
        addBubble("assistant", reply);
        history.push({ role: "assistant", content: reply });
        if (history.length > 16) history = history.slice(-16);

        var actions = result.data.actions || {};
        var wa = document.getElementById("jp-agent-wa");
        if (wa && actions.whatsapp_url) wa.href = actions.whatsapp_url;
        renderSuggestions(pickSuggestions(text));
      })
      .catch(function () {
        removeTyping();
        addBubble("assistant", "Problema de conexão. Use o WhatsApp.");
        renderSuggestions(["Continuar no WhatsApp"]);
      })
      .finally(function () {
        busy = false;
        if (input) {
          input.disabled = false;
          input.focus();
        }
      });
  }

  document.addEventListener("DOMContentLoaded", buildUI);
})();
