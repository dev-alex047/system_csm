<script>
async function validarLogin() {
    const usuario = document.getElementById('usuario').value.trim();
    const contrasena = document.getElementById('contrasena').value;

    try {
        const resp = await fetch('backend/api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: usuario, password: contrasena })
        });

        const data = await resp.json();

        if (!resp.ok || !data.success) {
            const msg = data.error || 'Credenciales incorrectas';
            alert(msg);
            return false;
        }

        //  Login exitoso
        window.location.href = 'index_mylove';
    } catch (err) {
        console.error('Login error:', err);
        alert('Error al iniciar sesión. Intenta de nuevo.');
    }

    return false;
}
</script>
<form onsubmit="return validarLogin()">
    <!-- ...existing code... -->
    <input type="text" id="usuario" name="usuario" />
    <input type="password" id="contrasena" name="contrasena" />
    <!-- ...existing code... -->
</form>