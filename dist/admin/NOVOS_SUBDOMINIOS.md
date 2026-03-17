# Novos subdomínios LinkBio — Checklist

Use este guia quando criar um **novo subdomínio** (ex.: `novocliente.linkbio.api.br`) para que o site e o **analytics** funcionem igual à Paty e ao Marcos.

---

## 1. Domínio e URLs (fixo)

- **Domínio principal:** `linkbio.api.br` (não usar linkbio.app.br)
- **Tracker:** `https://linkbio.api.br/tracker.js`
- **API de analytics:** `https://linkbio.api.br/admin/api/track.php`

---

## 2. Em cada página do subdomínio (HTML)

No final do `<body>`, antes de `</body>`, incluir **uma única linha**:

```html
<script src="https://linkbio.api.br/tracker.js" data-slug="SLUG_DO_CLIENTE"></script>
```

Troque `SLUG_DO_CLIENTE` pelo slug desse subdomínio (ex.: `paty`, `marcosblea`, `novocliente`).

- O **slug** deve ser: só letras minúsculas, números, hífen ou underscore (ex.: `novo-cliente` ou `novocliente`).
- Esse mesmo slug será usado no painel e no banco.

---

## 3. No banco de dados (painel admin)

Para o subdomínio aparecer no dashboard e os dados serem salvos:

1. **Criar usuário** em **Admin → Usuários → Novo usuário**:
   - **Nome:** nome do cliente (ex.: Marcos Bléa)
   - **Usuário:** login do cliente (ex.: `marcos`)
   - **Senha:** definir uma senha
   - **Perfil:** Cliente
   - **Slug da página:** **exatamente** o mesmo valor usado no `data-slug` do script (ex.: `marcosblea`)

2. Ou via SQL no phpMyAdmin:

```sql
INSERT INTO users (username, password_hash, role, page_slug, name)
VALUES (
  'usuario.do.cliente',
  'HASH_DA_SENHA',  -- use password_hash() no PHP ou gere um hash bcrypt
  'client',
  'slug-da-pagina',  -- ex: novocliente (mesmo do data-slug)
  'Nome do Cliente'
);
```

(O ideal é criar pelo painel **Usuários** para gerar o hash da senha.)

---

## 4. Na hospedagem (Hostinger)

1. **Subdomínio:** criar o subdomínio (ex.: `novocliente.linkbio.api.br`) apontando para uma pasta (ex.: `public_html/novocliente` ou `public_html/marcosblea`).
2. **Arquivos:** enviar o conteúdo da pasta do projeto (ex.: `dist/novocliente/` ou a pasta que você usar para esse cliente) para essa pasta do subdomínio.
3. **Raiz do subdomínio:** garantir que o `index.html` esteja na **raiz** dessa pasta (o que a Hostinger usa como document root do subdomínio).

---

## 5. CORS e API (já configurado)

A API `admin/api/track.php` já está configurada para:

- Aceitar requisições do domínio principal e de **todos** os subdomínios `*.linkbio.api.br` (e `*.linkbio.app.br` se usar).
- Enviar `Access-Control-Allow-Origin` com a origem exata e `Access-Control-Allow-Credentials: true` quando a origem for permitida, para evitar bloqueio de CORS quando o navegador envia credenciais.

Ao adicionar um subdomínio novo, **não é necessário** alterar a API: qualquer subdomínio `*.linkbio.api.br` já é aceito.

---

## 6. Checklist rápido (novo subdomínio)

- [ ] Slug definido (ex.: `novocliente`) e usado em todo lugar igual.
- [ ] No HTML do site: `<script src="https://linkbio.api.br/tracker.js" data-slug="novocliente"></script>` antes de `</body>`.
- [ ] Usuário criado no painel (ou no banco) com **page_slug** = esse mesmo slug.
- [ ] Subdomínio criado na Hostinger e pasta com `index.html` (e demais arquivos) enviados.
- [ ] Teste: abrir o subdomínio, F12 → Rede → recarregar → verificar requisição para `track.php` com status 200.
- [ ] No painel: selecionar o cliente e ver visitas/cliques.

---

*Documento criado para referência ao adicionar novos subdomínios (ex.: Paty, Marcos Bléa). Última atualização: março 2026.*
