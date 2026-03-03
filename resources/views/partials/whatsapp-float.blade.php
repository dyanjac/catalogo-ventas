<style>
  .whatsapp-float {
    position: fixed;
    right: 20px;
    bottom: 20px;
    width: 58px;
    height: 58px;
    border-radius: 9999px;
    background: #25D366;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
    z-index: 1200;
    text-decoration: none;
    transition: transform .2s ease, box-shadow .2s ease;
  }

  .whatsapp-float:hover {
    color: #ffffff;
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.28);
  }

  .whatsapp-float:focus-visible {
    outline: 2px solid #ffffff;
    outline-offset: 2px;
  }
</style>

@if(!empty($commerce['mobile_digits']))
  <a
    href="{{ $commerce['whatsapp_url'] }}?text=Hola%2C%20quiero%20realizar%20un%20pedido."
    class="whatsapp-float"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="Realizar pedido por WhatsApp"
    title="Realizar pedido por WhatsApp"
  >
    <i class="fab fa-whatsapp" aria-hidden="true"></i>
  </a>
@endif
