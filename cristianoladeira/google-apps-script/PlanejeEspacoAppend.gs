/**
 * Google Apps Script — grava respostas do formulário "Planeje seu espaço" na planilha.
 *
 * CONFIGURAÇÃO (uma vez):
 * 1) Abra ou crie uma planilha no Google Sheets (de preferência só para essas respostas).
 * 2) Na própria planilha: Extensões > Apps Script — cole este arquivo e salve
 *    (o script precisa estar vinculado à planilha, não um projeto Apps Script avulso).
 * 3) Ícone engrenagem ⚙ > Propriedades do projeto > Propriedades do script > Adicionar propriedade:
 *    Nome: PLANEJE_TOKEN
 *    Valor: uma senha longa aleatória (a mesma será colada em planeje-espaco.html).
 * 4) No editor, use Executar em qualquer função (ex.: doGet) uma vez para autorizar o script.
 *    A primeira resposta cria cabeçalhos na aba "Respostas" se ela estiver vazia (use a mesma aba do arquivo planeje-espaco-campos.xlsx).
 * 5) Implantar > Nova implantação > Tipo: App da Web
 *    - Executar como: Eu
 *    - Quem tem acesso: Qualquer pessoa  (OBRIGATÓRIO para o site público enviar dados)
 *      Se estiver "Somente eu" ou só contas Google, costuma dar 401/403 e nada grava na planilha.
 *      403 também pode ser política do Google Workspace (administrador).
 *    - Publique e, se mudar algo, crie NOVA VERSÃO na mesma implantação.
 * 6) Copie a URL do Web App (termina em /exec) e use no site.
 * 7) Se alterar o código, crie uma NOVA versão em Implantar > Gerenciar implantações > Editar > Versão.
 *
 * Planilha de destino (trecho .../spreadsheets/d/ESTE_ID/edit na URL do navegador):
 */

var SPREADSHEET_ID = '1XID3Fh88an21DThBNysiP43BtrIAUxjaYZOMs6q3GqI';

function getSpreadsheet() {
  return SpreadsheetApp.openById(SPREADSHEET_ID);
}

function getTargetSheet() {
  var ss = getSpreadsheet();
  var NAME = 'Respostas';
  var sh = ss.getSheetByName(NAME);
  if (!sh) {
    sh = ss.insertSheet(NAME);
  }
  return sh;
}

function doGet(e) {
  if (e && e.parameter && e.parameter.ping === '1') {
    return ContentService
      .createTextOutput(JSON.stringify({ ok: true, message: 'PlanejeEspaco ativo' }))
      .setMimeType(ContentService.MimeType.JSON);
  }
  return ContentService.createTextOutput('Use POST com payload (veja planeje-espaco.html).');
}

function doPost(e) {
  var lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    var props = PropertiesService.getScriptProperties();
    var secret = props.getProperty('PLANEJE_TOKEN');
    if (!secret) {
      return jsonOut({ ok: false, error: 'Configure PLANEJE_TOKEN nas propriedades do script.' });
    }

    var payload = null;
    if (e.postData && e.postData.contents) {
      try {
        payload = JSON.parse(e.postData.contents);
      } catch (err1) {}
    }
    if (!payload && e.parameter && e.parameter.payload) {
      try {
        payload = JSON.parse(e.parameter.payload);
      } catch (err2) {}
    }

    if (!payload || typeof payload !== 'object') {
      return jsonOut({ ok: false, error: 'Payload inválido.' });
    }

    if (payload.token !== secret) {
      return jsonOut({ ok: false, error: 'Não autorizado.' });
    }

    var sh = getTargetSheet();
    setupHeadersIfEmpty(sh);

    var now = new Date();
    var row = [
      now,
      String(payload.nome || ''),
      String(payload.email || ''),
      String(payload.telefone || ''),
      String(payload.origem || ''),
      String(payload.harasEmpresa || ''),
      payload.baias !== undefined && payload.baias !== '' ? Number(payload.baias) : '',
      payload.colaboradores !== undefined && payload.colaboradores !== '' ? Number(payload.colaboradores) : '',
      String(payload.banheiro || ''),
      String(payload.cozinha || ''),
      String(payload.receptivo || ''),
      String(payload.seguranca || ''),
      String(payload.limpeza || ''),
      String(payload.demanda || ''),
      String(payload.identidade || '')
    ];
    sh.appendRow(row);

    return jsonOut({ ok: true });
  } catch (err) {
    return jsonOut({ ok: false, error: String(err.message || err) });
  } finally {
    lock.releaseLock();
  }
}

function setupHeadersIfEmpty(sh) {
  if (sh.getLastRow() === 0) {
    sh.getRange(1, 1, 1, 15).setValues([[
      'Data/hora',
      'Nome',
      'E-mail',
      'Telefone',
      'Por onde veio',
      'Haras/Empresa',
      'Baias',
      'Colaboradores',
      'Banheiro (sanitário + chuveiro)',
      'Cozinha',
      'Receptivo',
      'Segurança',
      'Limpeza periódica',
      'Demanda específica',
      'Identidade visual'
    ]]);
  }
}

function jsonOut(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
