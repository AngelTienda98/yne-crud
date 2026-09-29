<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | Examen YNE</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>
<body>
<div class="container mt-5">

    <div class="row justify-content-center">

        <div class="col-md-5">

            <div class="card shadow">

                <div class="card-body">

                    <h3 class="text-center mb-4">
                        Examen YNE
                    </h3>

                    <form id="loginForm">

                        <div class="mb-3">

                            <label>
                                Usuario
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="username"
                                name="username"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label>
                                Contraseña
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                required
                            >

                        </div>

                        <div class="mb-3 text-center">
                            <label>Usuarios: <b>admin / moderador / ayudante </b></label>
                            <br>
                            <label>Contraseña: <b>123456</b></label>
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100">

                            Iniciar sesión

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="public/js/auth/login.js"></script>
</body>
</html>