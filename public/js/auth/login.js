document
    .getElementById("loginForm")
    .addEventListener("submit", async function(e) {

        e.preventDefault();

        const formData =
            new FormData(this);

        try {

            const response =
                await fetch(
                    "modules/auth/controller/LoginController.php?accion=login",
                    {
                        method: "POST",
                        body: formData
                    }
                );

            const data =
                await response.json();

            if (!data.success) {

                Swal.fire({
                    icon: "error",
                    title: "Acceso denegado",
                    text: data.message
                });

                return;
            }

            Swal.fire({
                icon: "success",
                title: "Bienvenido",
                text: data.message,
                timer: 1000,
                showConfirmButton: false
            }).then(() => {

                window.location.href =
                    "public/dashboard.php";

            });

        } catch (error) {

            console.error(error);

            Swal.fire({
                icon: "error",
                title: "Error",
                text: "No se pudo conectar con el servidor"
            });

        }

    });