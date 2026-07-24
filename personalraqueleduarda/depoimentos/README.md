# Depoimentos com login Google

Alunos entram em `/depoimentos/`, autenticam com Google e enviam comentário. A Raquel aprova no painel; só então aparecem no carrossel da home (`api/testimonials.php`).

## 1. Banco de dados

No phpMyAdmin (`u179630068_linkbio_bd`):

1. Se a tabela ainda não existe: `admin/sql/12_testimonials.sql`
2. Se a tabela já existe: rode também `admin/sql/13_testimonials_moderation.sql`  
   (novos depoimentos entram como pendentes)

## 2. Google Cloud (OAuth)

1. [Google Cloud Console](https://console.cloud.google.com/)
2. Projeto + tela de consentimento **Externo**
3. Credencial OAuth **Aplicativo da Web**
   - Origem: `https://personalraqueleduarda.linkbio.api.br`
   - Redirect: `https://personalraqueleduarda.linkbio.api.br/depoimentos/callback.php`

## 3. Config no servidor

Edite `depoimentos/config.php`:

```php
'google_client_id'     => '...',
'google_client_secret' => '...',
'admin_user'           => 'raquel',
'admin_password'       => 'sua_senha_forte',
```

## 4. Painel de moderação

URL: https://personalraqueleduarda.linkbio.api.br/depoimentos/painel/

Ações:
- **Aprovar** — aparece no site
- **Ocultar** — some do site (fica no banco como pendente)
- **Excluir** — remove de vez

Abas: Pendentes | Aprovados | Todos

## 5. Deploy

1. `build.ps1` → enviar `dist/personalraqueleduarda`
2. Atualizar `config.php` no Hostinger (senha do painel + Google)
3. Rodar SQL `13_testimonials_moderation.sql` se a tabela já existia

## Regras

- Novos comentários entram com `approved = 0` (pendentes)
- Máximo 2 comentários por dia por conta Google
- Nome e foto vêm do Google
