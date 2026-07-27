# Depoimentos e Transformações (Raquel Eduarda)

## Depoimentos (Google)

Alunos entram em `/depoimentos/`, autenticam com Google e enviam comentário. A Raquel aprova no painel; só então aparecem no carrossel da home (`api/testimonials.php`).

### Banco — depoimentos

No phpMyAdmin (`u179630068_linkbio_bd`):

1. Se a tabela ainda não existe: `admin/sql/12_testimonials.sql`
2. Se a tabela já existe: rode também `admin/sql/13_testimonials_moderation.sql`  
   (novos depoimentos entram como pendentes)

### Google Cloud (OAuth)

1. [Google Cloud Console](https://console.cloud.google.com/)
2. Projeto + tela de consentimento **Externo**
3. Credencial OAuth **Aplicativo da Web**
   - Origem: `https://personalraqueleduarda.linkbio.api.br`
   - Redirect: `https://personalraqueleduarda.linkbio.api.br/depoimentos/callback.php`

## Transformações (antes e depois)

A Raquel cadastra fotos e textos no painel. A home carrega via `api/transformations.php`.

### Banco — transformações

Execute: `admin/sql/14_transformations.sql`  
(cria a tabela e importa as 3 transformações atuais, se ainda não existirem)

### Painel

URL: https://personalraqueleduarda.linkbio.api.br/depoimentos/painel/

Módulos na lateral:
- **Depoimentos** — aprovar / ocultar / excluir
- **Transformações** — listar, cadastrar, editar, publicar / ocultar / excluir

Cadastro de transformação:
- Foto (antes/depois) — JPG/PNG/WEBP/GIF até 5 MB → pasta `antes-depois/`
- Objetivo, rótulo do perfil, perfil, resultado em, ordem, publicado

Páginas:
- Lista: `/depoimentos/painel/transformacoes.php`
- Nova/editar: `/depoimentos/painel/transformacao.php`

## Config no servidor

Edite `depoimentos/config.php`:

```php
'google_client_id'     => '...',
'google_client_secret' => '...',
'admin_user'           => 'raquel',
'admin_password'       => 'sua_senha_forte',
```

## Deploy

1. `build.ps1` → enviar `dist/personalraqueleduarda`
2. Garantir permissão de escrita em `antes-depois/` no Hostinger
3. Atualizar `config.php` no Hostinger (senha do painel + Google)
4. Rodar SQL `14_transformations.sql` (e `13_…` se ainda não tiver rodado)

## Regras

### Depoimentos
- Novos comentários entram com `approved = 0` (pendentes)
- Máximo 2 comentários por dia por conta Google
- Nome e foto vêm do Google

### Transformações
- Só itens com `published = 1` aparecem na home
- Ordenação por `sort_order` (menor primeiro)
