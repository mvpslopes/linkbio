<?php

function thayna_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function thayna_table_clientes_ok(PDO $pdo): bool
{
    return in_array('thayna_clientes', array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0), true);
}

function thayna_token_is_valid(string $token): bool
{
    return (bool) preg_match('/^(?:[a-fA-F0-9]{48}|[A-Za-z0-9_-]{12})$/', $token);
}

function thayna_gerar_token_cliente(?PDO $pdo = null): string
{
    do {
        $token = rtrim(strtr(base64_encode(random_bytes(9)), '+/', '-_'), '=');
        if ($pdo === null) {
            return $token;
        }
        $st = $pdo->prepare('SELECT 1 FROM thayna_clientes WHERE token = ? LIMIT 1');
        $st->execute([$token]);
    } while ($st->fetch());

    return $token;
}

function thayna_termo_url(string $token): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'thayna.linkbio.api.br';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
    return $scheme . '://' . $host . '/termo/' . rawurlencode($token);
}

function thayna_cliente_status(array $row): string
{
    if (!empty($row['assinado_em'])) {
        return 'assinado';
    }
    if (!empty($row['questionario_json'])) {
        return 'questionario';
    }
    return 'pendente';
}

function thayna_cliente_status_label(string $status): string
{
    return match ($status) {
        'assinado' => 'Termo assinado',
        'questionario' => 'Aguardando assinatura',
        default => 'Aguardando preenchimento',
    };
}

function thayna_questionario_from_post(array $post): array
{
    return [
        'motivo' => trim($post['motivo'] ?? ''),
        'sentimento' => trim($post['sentimento'] ?? ''),
        'relacionamento_atual' => trim($post['relacionamento_atual'] ?? ''),
        'tempo_relacionamento' => trim($post['tempo_relacionamento'] ?? ''),
        'inseguranca' => trim($post['inseguranca'] ?? ''),
        'traicao_antes' => trim($post['traicao_antes'] ?? ''),
        'traicao_detalhe' => trim($post['traicao_detalhe'] ?? ''),
        'expectativa' => trim($post['expectativa'] ?? ''),
        'preparada' => trim($post['preparada'] ?? ''),
        'info_importante' => trim($post['info_importante'] ?? ''),
    ];
}

function thayna_questionario_labels(): array
{
    return [
        'motivo' => 'O que te fez procurar meu atendimento hoje?',
        'sentimento' => 'Como você está se sentindo emocionalmente neste momento?',
        'relacionamento_atual' => 'Você está em um relacionamento atualmente?',
        'tempo_relacionamento' => 'Há quanto tempo dura ou durou esse relacionamento?',
        'inseguranca' => 'O que mais tem te causado insegurança ou desconforto?',
        'traicao_antes' => 'Você já passou por traição ou decepção amorosa antes?',
        'traicao_detalhe' => 'Se sim, deseja compartilhar?',
        'expectativa' => 'O que você espera encontrar ou entender através deste atendimento?',
        'preparada' => 'Você se considera emocionalmente preparada para lidar com qualquer resultado?',
        'info_importante' => 'Existe algo importante sobre sua situação que eu preciso saber antes de te atender?',
    ];
}

function thayna_termo_texto_legal(): string
{
    return <<<'TXT'
TERMO DE ACEITE – PRESTAÇÃO DE SERVIÇO DE TESTE DE FIDELIDADE

Ao contratar os serviços prestados por Thayna / Intuição Feminina, a contratante declara estar de acordo com os termos abaixo:

1. OBJETO DO SERVIÇO

O serviço consiste na realização de um teste de fidelidade/comportamento, com o objetivo de observar reações, interações e possíveis condutas da pessoa informada pela contratante.

O trabalho será realizado com base nas informações fornecidas pela contratante, podendo envolver contato por redes sociais, aplicativos de mensagem ou outros meios digitais.

2. RESPONSABILIDADE DAS INFORMAÇÕES

A contratante declara que todas as informações fornecidas são verdadeiras e de sua responsabilidade.

Informações incompletas, incorretas ou falsas podem comprometer o resultado da análise, não gerando qualquer responsabilidade para a prestadora.

3. LIMITES DO SERVIÇO

A prestadora compromete-se a executar o serviço com profissionalismo, discrição e boa-fé.

Entretanto, a contratante declara estar ciente de que:

- Não há garantia de um resultado específico;
- O comportamento do terceiro testado depende exclusivamente das escolhas dele;
- O serviço não constitui investigação policial, jurídica ou perícia oficial.

O serviço consiste em análise comportamental baseada nas interações obtidas.

4. CONFIDENCIALIDADE

Todas as informações compartilhadas entre contratante e prestadora serão tratadas com sigilo e confidencialidade.

A prestadora compromete-se a não divulgar dados, conversas, imagens ou informações pessoais da contratante sem autorização.

5. USO DAS INFORMAÇÕES E CONSEQUÊNCIAS

A contratante reconhece que qualquer decisão tomada após o resultado do teste (como confrontos, término de relacionamento, discussões ou medidas legais) será de sua total responsabilidade.

A prestadora não se responsabiliza por consequências emocionais, pessoais, familiares ou jurídicas decorrentes do uso das informações obtidas.

6. PAGAMENTO

O serviço será iniciado somente após a confirmação do pagamento.

Em caso de desistência após o início da execução do serviço, não haverá reembolso, considerando o tempo, estratégia e dedicação já aplicados no atendimento.

7. CANCELAMENTO OU RECUSA

A prestadora reserva-se o direito de recusar ou cancelar atendimentos em situações que envolvam:

- ameaças;
- uso ilegal do serviço;
- perseguição;
- assédio;
- qualquer finalidade maliciosa.

Nesses casos, o atendimento poderá ser encerrado imediatamente.

8. ACEITE

Ao realizar o pagamento e prosseguir com a contratação, a contratante declara ter lido, compreendido e aceito integralmente este termo.
TXT;
}

function thayna_ip_hash(): ?string
{
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim(explode(',', (string) $ip)[0]);
    return $ip !== '' ? hash('sha256', $ip) : null;
}
