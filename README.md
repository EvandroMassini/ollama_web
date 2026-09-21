# Ollama Web PHP

Front-end web responsivo para usar um servidor [Ollama](https://ollama.com/) local ou remoto. Desenvolvido em PHP, JavaScript e CSS, sem framework e sem dependências obrigatórias de terceiros.

Este projeto foi criado com o auxílio do **OpenAI Codex**.

## Recursos

- Chat responsivo para computador e celular.
- URL do Ollama configurável e persistente pelo painel.
- Descoberta e seleção dos modelos instalados.
- Instalação remota de modelos por `/api/pull`.
- Pré-carregamento e liberação de modelos da CPU/GPU.
- Temperatura, contexto e `keep_alive` configuráveis.
- Cancelamento de solicitações no navegador.
- Histórico em sessão e exportação para Markdown.
- Formatação de Markdown básico, código, listas e tabelas.
- Botão para copiar respostas.
- Cronômetro ao vivo e duração total no servidor.
- Auditoria das ferramentas executadas.
- Análise de imagens com modelos multimodais.
- Anexos textuais, CSV, JSON, Markdown e código-fonte.
- Consultas PostgreSQL somente leitura.
- Descoberta de tabelas e colunas.
- Contagem eficiente de tabelas e registros.
- Geração de TXT, Markdown, CSV, JSON, XML, HTML e código.
- Geração nativa de PDF textual, DOCX e XLSX.
- Criação de ZIP com vários arquivos.
- Biblioteca de downloads temporários por sessão.
- Proteção CSRF e cabeçalhos básicos de segurança.

## Requisitos

- PHP 8.1 ou superior.
- Apache/XAMPP ou servidor compatível.
- Ollama acessível pelo servidor PHP.
- Extensões PHP: `curl`, `json`, `mbstring` e `fileinfo`.
- `zip` para criar ZIP, DOCX e XLSX.
- `pdo_pgsql` para ferramentas PostgreSQL.

O projeto inclui `composer.json` para documentar os requisitos e executar o lint, mas não exige `composer install` para funcionar.

## Instalação no XAMPP

1. Copie a pasta para `C:\xampp\htdocs\Ollama-Web-PHP`.
2. Copie `config.local.example.php` para `config.local.php`.
3. Edite somente `config.local.php` com os endereços e credenciais locais.
4. Habilite no `php.ini`:

```ini
extension=curl
extension=mbstring
extension=fileinfo
extension=zip
extension=pdo_pgsql
extension=pgsql
```

5. Reinicie o Apache.
6. Abra `http://localhost/Ollama-Web-PHP/`.

Também é possível configurar `OLLAMA_URL`, `OLLAMA_MODEL`, `DB_ENABLED`, `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASSWORD` como variáveis de ambiente.

Ao usar **Configurações → Testar e salvar**, a aplicação testa a nova URL e grava o resultado em `storage/runtime-settings.json`. Esse arquivo é ignorado pelo Git, protegido pelo Apache e passa a ser o padrão nas próximas sessões. O painel permanece aberto para mostrar uma confirmação ou o erro detalhado. Se o campo **Modelo** estiver vazio — por exemplo, enquanto o servidor anterior estiver desconectado — a aplicação consulta o novo servidor e seleciona automaticamente o primeiro modelo instalado.

## Ollama remoto

Por padrão, o Ollama escuta apenas localmente. Para uso em LAN, configure o servidor Ollama para escutar em `0.0.0.0:11434`, reinicie o serviço e libere a porta somente na rede privada. Não exponha a API diretamente à internet.

O painel aceita URLs HTTP ou HTTPS nas portas autorizadas por `allowed_ollama_ports` em `config.php`.

## Modelos recomendados

Para chat, imagens e ferramentas em uma GPU de 10 GB:

```text
qwen3.5:9b
ministral-3:8b
```

Para ferramentas rápidas em texto:

```text
lfm2.5:8b
```

Modelos precisam oferecer *tool calling* para acessar PostgreSQL ou criar downloads. Imagens exigem um modelo multimodal.

## Ajustes de geração

- **Temperatura:** menor produz respostas mais determinísticas; maior aumenta variedade.
- **Contexto:** quantidade máxima de tokens mantidos. Valores altos consomem VRAM.
- **Keep alive:** tempo que o Ollama mantém o modelo carregado, como `30m`, `1h` ou `0`.

Em GPUs de 10 GB, comece com contexto de 8.192 ou 16.384 tokens.

## Anexos

Imagens PNG, JPEG, WebP e GIF são enviadas ao Ollama em base64. Arquivos textuais são inseridos no contexto sem armazenamento permanente. O padrão permite cinco arquivos de até 10 MB, limitando cada texto a 50.000 caracteres.

PDF, DOCX e XLSX recebidos não são enviados diretamente, pois a API nativa do Ollama não os interpreta. Converta-os para texto ou Markdown antes do envio. Isso não impede que a interface **gere** esses formatos para download.

## Arquivos gerados

Peça, por exemplo:

```text
Crie um arquivo JavaScript com uma função para validar CPF.
```

```text
Crie uma página HTML com CSS e JavaScript e entregue tudo em ZIP.
```

```text
Consulte o banco e gere uma planilha XLSX com o resultado.
```

O modelo chama `criar_arquivo`; o PHP valida a extensão, cria o arquivo em uma pasta protegida e devolve um cartão de download. Os downloads recebem IDs aleatórios, pertencem à sessão e expiram em 24 horas.

Formatos autorizados são configurados em `artifacts.allowed_extensions`. O PDF nativo é indicado para relatórios textuais simples. Para XLSX, o modelo produz CSV e o PHP cria o pacote Office Open XML.

## PostgreSQL

As ferramentas disponíveis são:

- `banco_resumo`: conta e lista tabelas.
- `banco_estrutura`: apresenta tabelas e colunas.
- `banco_consultar`: executa `SELECT` ou `WITH` parametrizado.
- `banco_contar_registros_tabelas`: conta registros em todas as tabelas.

O PHP bloqueia comandos de escrita, limita tempo e quantidade de linhas e ativa `default_transaction_read_only`. Ainda assim, use um usuário PostgreSQL exclusivo com permissão somente `SELECT`.

## Fluxo da aplicação

```text
Usuário → Ollama → resposta direta
                 ↘ ferramenta solicitada
                    → validação PHP
                    → PostgreSQL ou arquivo
                    → resultado ao Ollama
                    → resposta final
```

O PHP não interpreta intenções por palavras-chave. A decisão de usar uma ferramenta pertence ao modelo; o servidor apenas valida e executa operações registradas.

## Segurança

- Não versione `config.local.php`.
- Não exponha Ollama ou PostgreSQL diretamente à internet.
- Use a aplicação em LAN/VPN ou adicione autenticação no Apache.
- Ative HTTPS ao atravessar redes não confiáveis.
- Downloads são protegidos por sessão e `.htaccess`.
- Operações mutáveis exigem token CSRF.
- Nomes, extensões, tamanhos e URLs são validados.
- O modelo nunca escolhe caminhos físicos no servidor.

## Desenvolvimento

Valide todos os arquivos PHP:

```bash
composer lint
```

O workflow em `.github/workflows/php.yml` executa essa verificação em pushes e pull requests.

## Estrutura

```text
api/                 Endpoints JSON e downloads
assets/              Interface CSS e JavaScript
classes/             Ollama, chat, memória, banco e segurança
classes/Ferramentas/ Ferramentas autorizadas
prompts/             Prompt do sistema
storage/artifacts/   Downloads temporários protegidos
logs/                Auditoria local
```

## Limitações

- Não há pesquisa na internet.
- O cancelamento do navegador pode não interromper imediatamente uma inferência já iniciada no servidor.
- O histórico é mantido por sessão, não em banco permanente.
- A qualidade das chamadas de ferramentas depende do modelo utilizado.
- A aplicação deve receber autenticação adicional antes de exposição pública.

## Licença

[MIT](LICENSE)
