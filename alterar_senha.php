<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$usuarios = json_decode(file_get_contents("usuarios.json"), true);
$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];

    if ($usuarios[$usuario] === $senha_atual) {
        $usuarios[$usuario] = $nova_senha;
        file_put_contents("usuarios.json", json_encode($usuarios, JSON_PRETTY_PRINT));
        $mensagem = "<div class='alert alert-success'>Senha alterada com sucesso!</div>";
    } else {
        $mensagem = "<div class='alert alert-danger'>Senha atual incorreta!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Alterar Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="height:100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow p-4">
                    <h4 class="text-center mb-3">🔑 Alterar Senha</h4>
                    <?php echo $mensagem; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Senha Atual</label>
                            <input type="password" name="senha_atual" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nova Senha</label>
                            <input type="password" name="nova_senha" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Salvar</button>
                        <a href="index.php" class="btn btn-secondary w-100 mt-2">Voltar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
