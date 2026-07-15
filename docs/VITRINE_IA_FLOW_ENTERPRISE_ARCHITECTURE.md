# Vitrine IA Flow — Arquitetura Enterprise Oficial

## Papel oficial

A Vitrine IA Flow não é produto separado. Ela é o motor oficial de automação de todo o Ecossistema Vitrine IA Pro.

Todo produto desenvolvido pela Factory deve utilizar esta infraestrutura de automação.

Produtos atendidos:

- TV Sumaré Enterprise
- Portal News Enterprise
- Guia Digital da Cidade
- Conheça Sua Cidade
- Social Media IA
- SISMED
- GOV360
- Cultura IA
- 3º Setor
- Produtos futuros

Nenhum produto deve possuir automações próprias duplicadas.

## Arquitetura oficial

```text
VITRINE IA PRO
  ↓
Centro Operacional Master / Mission Control
  ↓
API / Event Bus Laravel
  ↓
Vitrine IA Flow / n8n
```

O Centro Operacional Master não executa automações. Ele monitora, dispara, agenda, acompanha e registra.

Quem executa workflows é exclusivamente o n8n.

## Definição técnica

A Vitrine IA Flow é um Motor de Orquestração Enterprise.

Todo workflow deve ser pensado como serviço reutilizável e multproduto.

## Queues oficiais

- Provision Queue
- Deployment Queue
- Sales Queue
- Billing Queue
- Support Queue
- AI Queue
- Monitoring Queue
- Infrastructure Queue
- Notification Queue
- Workflow Queue
- Editorial Queue
- Social Queue
- Video Queue
- Analytics Queue
- Backup Queue

Cada fila deve ser independente, observável e reprocessável.

## Comunicação oficial

Padrão obrigatório:

```text
Laravel
  ↓ REST API
Webhook
  ↓
n8n
  ↓ Webhook
Laravel
```

Nunca acessar diretamente o banco interno do n8n a partir do Centro Operacional Master.

## Factory Automation

Fluxo oficial de provisionamento:

```text
Cliente
  ↓
Compra
  ↓
Pagamento
  ↓
Licença
  ↓
Provision Queue
  ↓
Docker
  ↓
Banco
  ↓
Domínio
  ↓
SSL
  ↓
Deploy
  ↓
Configuração Inicial
  ↓
Entrega
  ↓
Cliente Online
```

## Mission Control

O Centro Operacional Master deve consumir APIs para visualizar:

- Status dos workflows
- Filas
- Tempo de execução
- Falhas
- Retentativas
- Histórico
- Performance
- Saúde do n8n
- Docker
- APIs
- IA

## Regras

- Não duplicar workflows.
- Reutilizar componentes.
- Criar serviços desacoplados.
- Pensar em escala para centenas de produtos simultâneos.
- Manter o n8n invisível para o usuário final.
- Toda interação visual pertence ao Centro Operacional Master, não a esta frente.
