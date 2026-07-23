# Depoimentos com login Google

Alunos entram em `/depoimentos/`, autenticam com Google (gratuito) e publicam comentário com nome + foto da conta. Os depoimentos aparecem no carrossel da home via `api/testimonials.php`.

## 1. Banco de dados

No phpMyAdmin do banco `u179630068_linkbio_bd`, execute:

```
admin/sql/12_testimonials.sql
```

## 2. Google Cloud (OAuth)

1. Acesse [Google Cloud Console](https://console.cloud.google.com/)
2. Crie um projeto (ou use um existente)
3. **APIs e serviços → Tela de consentimento OAuth**  
   - Tipo: Externo  
   - Nome do app: Raquel Eduarda (ou similar)  
   - Escopos: `openid`, `email`, `profile` (padrão)
4. **APIs e serviços → Credenciais → Criar credenciais → ID do cliente OAuth**  
   - Tipo: **Aplicativo da Web**
   - **Origens JavaScript autorizadas:**
     - `https://personalraqueleduarda.linkbio.api.br`
   - **URIs de redirecionamento autorizados:**
     - `https://personalraqueleduarda.linkbio.api.br/depoimentos/callback.php`
5. Copie o **Client ID** e o **Client Secret**

## 3. Config no servidor

No Hostinger, edite:

`personalraqueleduarda/depoimentos/config.php`

(ou copie de `config.example.php` se ainda não existir)

Preencha:

```php
'google_client_id'     => '...apps.googleusercontent.com',
'google_client_secret' => '...',
```

Os demais campos já vêm corretos no exemplo.

## 4. Deploy

1. Rode o build (`build.ps1`) e envie a pasta `dist/personalraqueleduarda` (ou o sync habitual)
2. Confirme que no servidor existem:
   - `/depoimentos/` (PHP)
   - `/api/testimonials.php`
   - `config.php` com as credenciais reais
3. Teste:
   - `https://personalraqueleduarda.linkbio.api.br/depoimentos/`
   - Login Google → publicar → ver em `/#depoimentos`

## Regras

- Publicação imediata (`approved = 1`)
- Máximo 2 comentários por dia por conta Google
- Nome e foto vêm do Google (sem upload)
- Sem custo de API além do OAuth gratuito do Google
