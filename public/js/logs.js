$(document).ready(function () {
    mostrarLoading();
    $.ajax({
        type: "POST",
        url: `./api/getLogsActivosProcessed`,
        xhrFields: {
            withCredentials: true,
        },
        data: {
            fecha_inicio: "all",
            fecha_final: "all",
            tipo: "all",
        },
        success: function (retorno, textStatus, request) {
            console.log(retorno);
            for (let log of retorno["deleted_activos"]) {
                console.log(log)
                $('#DelActivos').append('<l1 class="mt-1">' + log + '</l1>  <hr>');
            }
            for (let log of retorno["modified_activos"]) {
                console.log(log)
                $('#ModActivos').append('<l1 class="mt-1">' + log + '</l1>  <hr>');
            }
            for (let log of retorno["new_activos"]) {
                console.log(log)
                $('#NewActivos').append('<l1 class="mt-1">' + log + '</l1>  <hr>');
            }
            for (let log of retorno["relation_changes"]) {
                console.log(log)
                $('#ChangeActivos').append('<l1 class="mt-1">' + log + '</l1>  <hr>');
            }
            cerrarModal();
            $('.section-title').click(function() {
                $(this).next('ul').toggleClass('active');
            });
        },
    });
}); 