<?php
/**
 * Shell-view (zie index.php in deze map voor uitleg): data komt via /api/v1/tickets/{id}, gerenderd
 * door public/assets/js/pages/tickets-show.js. Het ticket-id wordt via het routeparameter uit de URL
 * gehaald (laatste padsegment), niet via een PHP-variabele — de route/controller-signature van deze
 * pagina blijft ongewijzigd t.o.v. de bestaande TicketController::show(), alleen de view-inhoud niet
 * meer gebruikt.
 */
?>
<div id="ticket-app" data-page="tickets-show"></div>
<script type="module" src="/assets/js/pages/tickets-show.js"></script>
