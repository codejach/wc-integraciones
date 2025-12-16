<div class="wrap">
    <h1>MercadoLibre</h1>
    <h2 class="nav-tab-wrapper">
        <a href="?page=integraciones-woocommerce-mercadolibre&tab=panel" class="nav-tab <?php echo $this->get_active_tab('panel'); ?>">Panel</a>
        <a href="?page=integraciones-woocommerce-mercadolibre&tab=wc2meli" class="nav-tab <?php echo $this->get_active_tab('wc2meli'); ?>">WC a Meli</a>
        <a href="?page=integraciones-woocommerce-mercadolibre&tab=meli2wc" class="nav-tab <?php echo $this->get_active_tab('meli2wc'); ?>">Meli a WC</a>
        <a href="?page=integraciones-woocommerce-mercadolibre&tab=registros" class="nav-tab <?php echo $this->get_active_tab('registros'); ?>">Registros</a>
        <a href="?page=integraciones-woocommerce-mercadolibre&tab=log" class="nav-tab <?php echo $this->get_active_tab('log'); ?>">Log</a>
        <a href="?page=integraciones-woocommerce-mercadolibre&tab=configuracion" class="nav-tab <?php echo $this->get_active_tab('configuracion'); ?>">Configuración</a>
    </h2>
    <div class="tab-content">
        <?php $this->display_tab_content(); ?>
    </div>
</div>
