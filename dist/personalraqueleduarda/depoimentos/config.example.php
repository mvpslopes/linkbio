<?php
/**
 * Copie este arquivo para config.php e preencha as credenciais.
 *
 * Google Cloud Console → APIs e serviços → Credenciais → ID do cliente OAuth
 * Tipo: Aplicativo da Web
 *
 * URIs de redirecionamento autorizados:
 *   https://personalraqueleduarda.linkbio.api.br/depoimentos/callback.php
 *
 * Origens JavaScript autorizadas:
 *   https://personalraqueleduarda.linkbio.api.br
 *
 * Painel de moderação:
 *   https://personalraqueleduarda.linkbio.api.br/depoimentos/painel/
 */
return [
    'page_slug'       => 'personalraqueleduarda',
    'site_name'       => 'Raquel Eduarda',
    'google_client_id'     => 'SEU_CLIENT_ID.apps.googleusercontent.com',
    'google_client_secret' => 'SEU_CLIENT_SECRET',
    // Deixe vazio para detectar automaticamente a partir da URL atual
    'base_url'        => 'https://personalraqueleduarda.linkbio.api.br/depoimentos',
    'main_site_url'   => 'https://personalraqueleduarda.linkbio.api.br/',
    'max_comments_per_day' => 2,
    // Login do painel (aprovar / ocultar / excluir)
    'admin_user'      => 'raquel',
    'admin_password'  => 'TROCAR_ESTA_SENHA',
];
