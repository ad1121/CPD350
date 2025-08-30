<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];

// ===============================
// Carregar demandas do JSON
// ===============================
if (!file_exists("demandas.json")) {
    file_put_contents("demandas.json", json_encode([]));
}
$demandas = json_decode(file_get_contents("demandas.json"), true);

// ===============================
// Carregar comentários do JSON
// ===============================
if (!file_exists("comentarios.json")) {
    file_put_contents("comentarios.json", json_encode([]));
}
$comentarios = json_decode(file_get_contents("comentarios.json"), true);

// ===============================
// Criar nova demanda
// ===============================
if (isset($_POST['nova_titulo']) && isset($_POST['nova_descricao'])) {
    $nova = [
        "id" => count($demandas) > 0 ? max(array_column($demandas, "id")) + 1 : 1,
        "titulo" => $_POST['nova_titulo'],
        "descricao" => $_POST['nova_descricao'],
        "status" => "pendente", // pendente, aceita, concluida, finalizada
        "responsavel" => null, // quem aceitou a demanda
        "data_criacao" => date("Y-m-d H:i:s"),
        "data_aceite" => null,
        "data_finalizacao" => null
    ];
    $demandas[] = $nova;
    file_put_contents("demandas.json", json_encode($demandas, JSON_PRETTY_PRINT));
    
    // Retorna resposta JSON para atualização em tempo real
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'demanda' => $nova]);
        exit;
    }
    
    header("Location: index.php");
    exit;
}

// ===============================
// Aceitar demanda
// ===============================
if (isset($_POST['aceitar']) && isset($_POST['id'])) {
    $id_demanda = $_POST['id'];
    foreach ($demandas as &$d) {
        if ($d["id"] == $id_demanda) {
            $d["status"] = "aceita";
            $d["responsavel"] = $usuario;
            $d["data_aceite"] = date("Y-m-d H:i:s");
            break;
        }
    }
    file_put_contents("demandas.json", json_encode($demandas, JSON_PRETTY_PRINT));
    header("Location: index.php#minhas");
    exit;
}

// ===============================
// Comentários
// ===============================
if (isset($_POST['comentario']) && isset($_POST['id'])) {
    $id_demanda = $_POST['id'];
    $aba_atual = $_POST['aba_atual'] ?? 'minhas';
    
    if (!isset($comentarios[$id_demanda])) {
        $comentarios[$id_demanda] = [];
    }
    $comentarios[$id_demanda][] = [
        "usuario" => $usuario,
        "texto" => $_POST['comentario'],
        "data" => date("Y-m-d H:i:s")
    ];
    file_put_contents("comentarios.json", json_encode($comentarios, JSON_PRETTY_PRINT));
    header("Location: index.php#" . $aba_atual);
    exit;
}

// ===============================
// Marcar como concluída (Maycon/Jader/Fabio)
// ===============================
if (isset($_POST['concluir']) && isset($_POST['id'])) {
    $id_demanda = $_POST['id'];
    foreach ($demandas as &$d) {
        if ($d["id"] == $id_demanda && $d["responsavel"] == $usuario) {
            $d["status"] = "concluida";
            break;
        }
    }
    file_put_contents("demandas.json", json_encode($demandas, JSON_PRETTY_PRINT));
    header("Location: index.php#minhas");
    exit;
}

// ===============================
// Finalizar de vez (Denilson)
// ===============================
if (isset($_POST['finalizar_de_vez']) && isset($_POST['id'])) {
    $id_demanda = $_POST['id'];
    foreach ($demandas as &$d) {
        if ($d["id"] == $id_demanda) {
            $d["status"] = "finalizada";
            $d["data_finalizacao"] = date("Y-m-d H:i:s");
            break;
        }
    }
    file_put_contents("demandas.json", json_encode($demandas, JSON_PRETTY_PRINT));
    header("Location: index.php#concluidas");
    exit;
}

// ===============================
// API para fornecer todos os dados
// ===============================
if (isset($_GET['get_all_data'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'demandas' => $demandas,
        'comentarios' => $comentarios,
        'usuario_atual' => $usuario
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>GESTÃO CPD 350</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { background-color: #f8f9fa; }
            50% { background-color: #e3f2fd; }
            100% { background-color: #f8f9fa; }
        }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>🖥️ GESTÃO CPD 350</h2>
        <div>
            <span id="status-connection" class="badge bg-success me-2">Online</span>
            <a href="alterar_senha.php" class="btn btn-secondary btn-sm">Alterar Senha</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Sair</a>
        </div>
    </div>
    <p>Usuário logado: <b><?php echo $usuario; ?></b></p>

    <!-- Formulário para criar nova demanda -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">➕ Criar Nova Demanda</h5>
            <form id="form-nova-demanda" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="nova_titulo" class="form-control" placeholder="Título da demanda" required>
                </div>
                <div class="col-md-5">
                    <input type="text" name="nova_descricao" class="form-control" placeholder="Descrição" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100">Adicionar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Abas -->
    <ul class="nav nav-tabs mb-3" id="main-tabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#disponiveis">
                Disponíveis <span id="badge-disponiveis" class="badge bg-info ms-1"></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#minhas">
                Minhas Demandas <span id="badge-minhas" class="badge bg-warning ms-1"></span>
            </button>
        </li>
        <?php if ($usuario === "Denilson"): ?>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#concluidas">
                Aguardando Aprovação <span id="badge-concluidas" class="badge bg-success ms-1"></span>
            </button>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#finalizadas">
                Finalizadas <span id="badge-finalizadas" class="badge bg-secondary ms-1"></span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Demandas Disponíveis -->
        <div class="tab-pane fade show active" id="disponiveis">
            <h5>🆓 Demandas Disponíveis para Aceitar</h5>
            <div class="row" id="container-disponiveis">
                <?php foreach ($demandas as $d): ?>
                    <?php if ($d["status"] === "pendente"): ?>
                        <div class="col-md-4 mb-3" data-demanda-id="<?php echo $d['id']; ?>">
                            <div class="card shadow-sm h-100 border-info">
                                <div class="card-body">
                                    <h5 class="card-title text-info"><?php echo htmlspecialchars($d["titulo"]); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($d["descricao"]); ?></p>
                                    <p class="text-muted small">Criada em: <?php echo date("d/m/Y H:i", strtotime($d["data_criacao"])); ?></p>
                                    
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?php echo $d["id"]; ?>">
                                        <button type="submit" name="aceitar" class="btn btn-info w-100">✋ Aceitar Demanda</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Minhas Demandas -->
        <div class="tab-pane fade" id="minhas">
            <h5>👤 Demandas sob Minha Responsabilidade</h5>
            <div class="row" id="container-minhas">
                <?php foreach ($demandas as $d): ?>
                    <?php if ($d["status"] === "aceita" && $d["responsavel"] === $usuario): ?>
                        <div class="col-md-4 mb-3" data-demanda-id="<?php echo $d['id']; ?>">
                            <div class="card shadow-sm h-100 border-warning">
                                <div class="card-body">
                                    <h5 class="card-title text-warning"><?php echo htmlspecialchars($d["titulo"]); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($d["descricao"]); ?></p>
                                    <p class="text-muted small">
                                        Aceita em: <?php echo date("d/m/Y H:i", strtotime($d["data_aceite"])); ?>
                                    </p>

                                    <!-- Comentários -->
                                    <h6>💬 Comentários:</h6>
                                    <div class="border rounded p-2 bg-light small mb-2 comentarios-container" style="max-height:120px;overflow-y:auto;" data-demanda-id="<?php echo $d['id']; ?>">
                                        <?php
                                        if (!empty($comentarios[$d["id"]])) {
                                            foreach ($comentarios[$d["id"]] as $c) {
                                                echo "<p><b>".htmlspecialchars($c['usuario'])."</b> (".date("d/m H:i", strtotime($c['data']))."): ".htmlspecialchars($c['texto'])."</p>";
                                            }
                                        } else {
                                            echo "<p class='text-muted'>Nenhum comentário ainda.</p>";
                                        }
                                        ?>
                                    </div>

                                    <!-- Formulário de comentário -->
                                    <form method="post" class="d-flex mb-2">
                                        <input type="hidden" name="id" value="<?php echo $d["id"]; ?>">
                                        <input type="hidden" name="aba_atual" value="minhas">
                                        <input type="text" name="comentario" class="form-control me-2" placeholder="Escreva um comentário..." required>
                                        <button type="submit" class="btn btn-primary btn-sm">Comentar</button>
                                    </form>

                                    <!-- Botão de conclusão -->
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?php echo $d["id"]; ?>">
                                        <button type="submit" name="concluir" class="btn btn-warning w-100">✅ Marcar como Concluída</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($usuario === "Denilson"): ?>
        <!-- Aguardando Aprovação (só para Denilson) -->
        <div class="tab-pane fade" id="concluidas">
            <h5>⏳ Demandas Aguardando Aprovação Final</h5>
            <div class="row" id="container-concluidas">
                <?php foreach ($demandas as $d): ?>
                    <?php if ($d["status"] === "concluida"): ?>
                        <div class="col-md-4 mb-3" data-demanda-id="<?php echo $d['id']; ?>">
                            <div class="card shadow-sm h-100 border-success">
                                <div class="card-body">
                                    <h5 class="card-title text-success"><?php echo htmlspecialchars($d["titulo"]); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($d["descricao"]); ?></p>
                                    <p class="text-muted small">
                                        Responsável: <b><?php echo htmlspecialchars($d["responsavel"]); ?></b><br>
                                        Aceita em: <?php echo date("d/m/Y H:i", strtotime($d["data_aceite"])); ?>
                                    </p>

                                    <!-- Comentários -->
                                    <h6>💬 Comentários:</h6>
                                    <div class="border rounded p-2 bg-light small mb-2 comentarios-container" style="max-height:120px;overflow-y:auto;" data-demanda-id="<?php echo $d['id']; ?>">
                                        <?php
                                        if (!empty($comentarios[$d["id"]])) {
                                            foreach ($comentarios[$d["id"]] as $c) {
                                                echo "<p><b>".htmlspecialchars($c['usuario'])."</b> (".date("d/m H:i", strtotime($c['data']))."): ".htmlspecialchars($c['texto'])."</p>";
                                            }
                                        } else {
                                            echo "<p class='text-muted'>Nenhum comentário ainda.</p>";
                                        }
                                        ?>
                                    </div>

                                    <!-- Formulário de comentário -->
                                    <form method="post" class="d-flex mb-2">
                                        <input type="hidden" name="id" value="<?php echo $d["id"]; ?>">
                                        <input type="hidden" name="aba_atual" value="concluidas">
                                        <input type="text" name="comentario" class="form-control me-2" placeholder="Escreva um comentário..." required>
                                        <button type="submit" class="btn btn-primary btn-sm">Comentar</button>
                                    </form>

                                    <!-- Finalizar definitivamente -->
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?php echo $d["id"]; ?>">
                                        <button type="submit" name="finalizar_de_vez" class="btn btn-success w-100">🏁 Finalizar Definitivamente</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Finalizadas -->
        <div class="tab-pane fade" id="finalizadas">
            <h5>🏆 Demandas Finalizadas</h5>
            <div class="row" id="container-finalizadas">
                <?php foreach ($demandas as $d): ?>
                    <?php 
                    if ($d["status"] === "finalizada") {
                        // Para usuários comuns, só mostra as que ele foi responsável
                        if ($usuario !== "Denilson" && $d["responsavel"] !== $usuario) {
                            continue;
                        }
                    ?>
                        <div class="col-md-4 mb-3" data-demanda-id="<?php echo $d['id']; ?>">
                            <div class="card border-success shadow-sm h-100">
                                <div class="card-body">
                                    <h5 class="card-title text-success"><?php echo htmlspecialchars($d["titulo"]); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($d["descricao"]); ?></p>
                                    <p class="text-muted small">
                                        <b>Responsável:</b> <?php echo htmlspecialchars($d["responsavel"] ?? 'N/A'); ?><br>
                                        <b>Finalizada em:</b> <?php echo $d["data_finalizacao"] ? date("d/m/Y H:i", strtotime($d["data_finalizacao"])) : 'N/A'; ?>
                                    </p>
                                    
                                    <!-- Comentários (apenas visualização) -->
                                    <?php if (!empty($comentarios[$d["id"]])): ?>
                                    <h6>💬 Comentários:</h6>
                                    <div class="border rounded p-2 bg-light small comentarios-container" style="max-height:120px;overflow-y:auto;" data-demanda-id="<?php echo $d['id']; ?>">
                                        <?php
                                        foreach ($comentarios[$d["id"]] as $c) {
                                            echo "<p><b>".htmlspecialchars($c['usuario'])."</b> (".date("d/m H:i", strtotime($c['data']))."): ".htmlspecialchars($c['texto'])."</p>";
                                        }
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                    }
                    ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let lastCheck = new Date().toISOString().slice(0, 19).replace('T', ' ');

// Formulário AJAX para criar demanda
document.getElementById('form-nova-demanda').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('ajax', '1');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            this.reset();
            // A atualização será feita pelo polling
            document.getElementById('status-connection').textContent = 'Demanda criada!';
            document.getElementById('status-connection').className = 'badge bg-success me-2';
            setTimeout(() => {
                document.getElementById('status-connection').textContent = 'Online';
            }, 2000);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        document.getElementById('status-connection').textContent = 'Erro';
        document.getElementById('status-connection').className = 'badge bg-danger me-2';
    });
});

// Função para atualizar contadores das abas
function updateTabBadges() {
    const disponiveis = document.querySelectorAll('#container-disponiveis .col-md-4').length;
    const minhas = document.querySelectorAll('#container-minhas .col-md-4').length;
    const finalizadas = document.querySelectorAll('#container-finalizadas .col-md-4').length;
    
    document.getElementById('badge-disponiveis').textContent = disponiveis || '';
    document.getElementById('badge-minhas').textContent = minhas || '';
    document.getElementById('badge-finalizadas').textContent = finalizadas || '';
    
    const badgeConcluidas = document.getElementById('badge-concluidas');
    if (badgeConcluidas) {
        const concluidas = document.querySelectorAll('#container-concluidas .col-md-4').length;
        badgeConcluidas.textContent = concluidas || '';
    }
}

// Função para carregar todas as demandas e comentários
function carregarDados() {
    fetch('index.php?get_all_data=1')
    .then(response => response.json())
    .then(data => {
        atualizarInterface(data.demandas, data.comentarios);
        // Aguardar um pouco para o DOM ser atualizado antes de contar
        setTimeout(updateTabBadges, 100);
        
        document.getElementById('status-connection').textContent = 'Online';
        document.getElementById('status-connection').className = 'badge bg-success me-2';
    })
    .catch(error => {
        console.error('Erro ao carregar dados:', error);
        document.getElementById('status-connection').textContent = 'Offline';
        document.getElementById('status-connection').className = 'badge bg-warning me-2';
    });
}

// Função para atualizar a interface
function atualizarInterface(demandas, comentarios) {
    const usuario = '<?php echo $usuario; ?>';
    const isAdmin = usuario === 'Denilson';
    
    // Limpar containers
    document.getElementById('container-disponiveis').innerHTML = '';
    document.getElementById('container-minhas').innerHTML = '';
    document.getElementById('container-finalizadas').innerHTML = '';
    if (isAdmin) {
        document.getElementById('container-concluidas').innerHTML = '';
    }
    
    demandas.forEach(d => {
        const card = criarCardDemanda(d, comentarios[d.id] || [], usuario, isAdmin);
        
        if (d.status === 'pendente') {
            document.getElementById('container-disponiveis').appendChild(card);
        } else if (d.status === 'aceita' && d.responsavel === usuario) {
            document.getElementById('container-minhas').appendChild(card);
        } else if (d.status === 'concluida' && isAdmin) {
            document.getElementById('container-concluidas').appendChild(card);
        } else if (d.status === 'finalizada') {
            if (isAdmin || d.responsavel === usuario) {
                document.getElementById('container-finalizadas').appendChild(card);
            }
        }
    });
}

// Função para criar card de demanda
function criarCardDemanda(demanda, comentarios, usuario, isAdmin) {
    const col = document.createElement('div');
    col.className = 'col-md-4 mb-3';
    col.setAttribute('data-demanda-id', demanda.id);
    
    let borderClass = 'border-info';
    let titleClass = 'text-info';
    let botaoHtml = '';
    
    if (demanda.status === 'pendente') {
        borderClass = 'border-info';
        titleClass = 'text-info';
        botaoHtml = `
            <form method="post">
                <input type="hidden" name="id" value="${demanda.id}">
                <button type="submit" name="aceitar" class="btn btn-info w-100">✋ Aceitar Demanda</button>
            </form>`;
    } else if (demanda.status === 'aceita') {
        borderClass = 'border-warning';
        titleClass = 'text-warning';
        botaoHtml = `
            <form method="post">
                <input type="hidden" name="id" value="${demanda.id}">
                <button type="submit" name="concluir" class="btn btn-warning w-100">✅ Marcar como Concluída</button>
            </form>`;
    } else if (demanda.status === 'concluida' && isAdmin) {
        borderClass = 'border-success';
        titleClass = 'text-success';
        botaoHtml = `
            <form method="post">
                <input type="hidden" name="id" value="${demanda.id}">
                <button type="submit" name="finalizar_de_vez" class="btn btn-success w-100">🏁 Finalizar Definitivamente</button>
            </form>`;
    } else if (demanda.status === 'finalizada') {
        borderClass = 'border-success';
        titleClass = 'text-success';
        botaoHtml = '';
    }
    
    // Gerar HTML dos comentários
    let comentariosHtml = '';
    if (comentarios.length > 0) {
        comentarios.forEach(c => {
            const data = new Date(c.data).toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
            comentariosHtml += `<p><b>${escapeHtml(c.usuario)}</b> (${data}): ${escapeHtml(c.texto)}</p>`;
        });
    } else {
        comentariosHtml = '<p class="text-muted">Nenhum comentário ainda.</p>';
    }
    
    // Determinar aba atual para o formulário de comentário
    let abaAtual = 'disponiveis';
    if (demanda.status === 'aceita' && demanda.responsavel === usuario) abaAtual = 'minhas';
    else if (demanda.status === 'concluida' && isAdmin) abaAtual = 'concluidas';
    else if (demanda.status === 'finalizada') abaAtual = 'finalizadas';
    
    // Formulário de comentário (só se não for finalizada)
    let formComentario = '';
    if (demanda.status !== 'finalizada') {
        formComentario = `
            <form method="post" class="d-flex mb-2">
                <input type="hidden" name="id" value="${demanda.id}">
                <input type="hidden" name="aba_atual" value="${abaAtual}">
                <input type="text" name="comentario" class="form-control me-2" placeholder="Escreva um comentário..." required>
                <button type="submit" class="btn btn-primary btn-sm">Comentar</button>
            </form>`;
    }
    
    const dataInfo = demanda.status === 'pendente' 
        ? `Criada em: ${new Date(demanda.data_criacao).toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'})}`
        : demanda.status === 'aceita' 
        ? `Aceita em: ${new Date(demanda.data_aceite).toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'})}`
        : demanda.status === 'concluida' 
        ? `Responsável: <b>${escapeHtml(demanda.responsavel)}</b><br>Aceita em: ${new Date(demanda.data_aceite).toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'})}`
        : `<b>Responsável:</b> ${escapeHtml(demanda.responsavel || 'N/A')}<br><b>Finalizada em:</b> ${demanda.data_finalizacao ? new Date(demanda.data_finalizacao).toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'}) : 'N/A'}`;
    
    col.innerHTML = `
        <div class="card shadow-sm h-100 ${borderClass}">
            <div class="card-body">
                <h5 class="card-title ${titleClass}">${escapeHtml(demanda.titulo)}</h5>
                <p class="card-text">${escapeHtml(demanda.descricao)}</p>
                <p class="text-muted small">${dataInfo}</p>
                
                ${demanda.status !== 'pendente' ? `
                <h6>💬 Comentários:</h6>
                <div class="border rounded p-2 bg-light small mb-2 comentarios-container" style="max-height:120px;overflow-y:auto;" data-demanda-id="${demanda.id}">
                    ${comentariosHtml}
                </div>
                ${formComentario}
                ` : ''}
                
                ${botaoHtml}
            </div>
        </div>`;
    
    return col;
}

// Função auxiliar para escapar HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Função para carregar todas as demandas e comentários
function carregarDados() {
    fetch('index.php?get_all_data=1')
    .then(response => response.json())
    .then(data => {
        atualizarInterface(data.demandas, data.comentarios);
        updateTabBadges();
        
        document.getElementById('status-connection').textContent = 'Online';
        document.getElementById('status-connection').className = 'badge bg-success me-2';
    })
    .catch(error => {
        console.error('Erro ao carregar dados:', error);
        document.getElementById('status-connection').textContent = 'Offline';
        document.getElementById('status-connection').className = 'badge bg-warning me-2';
    });
}

// Verificar atualizações a cada 3 segundos
setInterval(carregarDados, 3000);

// Carregar dados iniciais
document.addEventListener('DOMContentLoaded', function() {
    carregarDados();
    
    const hash = window.location.hash;
    if (hash) {
        const tabButton = document.querySelector(`[data-bs-target="${hash}"]`);
        if (tabButton) {
            const tab = new bootstrap.Tab(tabButton);
            tab.show();
        }
    }
});

// Manter aba ativa após mudanças
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function(e) {
        const target = e.target.getAttribute('data-bs-target');
        window.location.hash = target;
    });
});
</script>
</body>
</html>