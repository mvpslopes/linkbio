<?php
/**
 * Copie para config.php e preencha.
 * Nunca exponha a chave Groq no front-end.
 */
return [
    'groq_api_key' => 'gsk_SUA_CHAVE_AQUI',
    'groq_model'   => 'llama-3.3-70b-versatile',
    'agent_id'     => 'linkbio_jessica',

    'whatsapp' => '5531983955337',
    'whatsapp_default_text' => 'Olá, Jéssica! Vim pelo assistente do site e quero saber mais.',

    // Preencha quando tiver os dados do produto
    'product' => [
        'name' => 'MFIT Personal',
        'tagline' => 'Aplicativo de prescrição de treinos com acesso exclusivo para alunos da Jéssica.',
        'price' => '',          // opcional; se vazio, o agente manda para o WhatsApp
        'includes' => [
            'Treinos prescritos pela Jéssica no app MFIT',
            'Vídeos dos exercícios e acompanhamento',
        ],
        'notes' => 'Venda e valores são fechados apenas pelo WhatsApp.',
    ],

    'max_history' => 16,
    'max_message_chars' => 800,
    'rate_limit_per_hour' => 40,
];
