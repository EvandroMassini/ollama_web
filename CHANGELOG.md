# Changelog

## 1.2.0 — 2026

- O texto provisório "Conectando…" deixou de ser tratado como nome de modelo.
- O campo de modelo permanece vazio quando o Ollama está indisponível.
- Após testar uma nova URL, o primeiro modelo instalado é selecionado e salvo automaticamente quando necessário.
- A confirmação das configurações agora informa explicitamente o modelo selecionado.

## 1.1.0 — 2026

- Identidade do projeto consolidada como Ollama Web PHP.
- Nomenclatura interna atualizada para refletir um cliente web completo do Ollama.
- Correção do salvamento da URL remota do Ollama.
- Configurações do painel persistidas em `storage/runtime-settings.json`.
- Confirmação e erros exibidos dentro do painel de configuração.

## 1.0.0 — 2026

- Chat remoto com Ollama e seleção de modelos.
- Instalação, carregamento e liberação remota de modelos.
- Ferramentas PostgreSQL somente leitura.
- Análise de imagens e arquivos textuais.
- Geração segura de código, CSV, PDF, DOCX, XLSX e ZIP.
- Biblioteca de downloads temporários.
- Cronômetro total e auditoria de ferramentas.
- Configuração de temperatura, contexto e permanência em memória.
- Proteção CSRF e configuração privada fora do Git.
