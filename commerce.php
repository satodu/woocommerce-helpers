<?php
function aplicar_cupom_personalizado() {
    // Verifica se o parâmetro 'coupon_code' está presente na URL
    if (isset($_GET['coupon_code'])) {
        $coupon_code = sanitize_text_field($_GET['coupon_code']);

        // Adiciona o cupom ao carrinho
        if (WC()->cart && !empty($coupon_code)) {
            $aplicado = WC()->cart->apply_coupon($coupon_code);

            if ($aplicado) {
                wc_add_notice("Cupom aplicado com sucesso!", 'success');
            } else {
                wc_add_notice("Erro ao aplicar o cupom. Verifique o código.", 'error');
            }
        }

        // Redireciona o usuário de volta ao carrinho ou checkout
        wp_redirect(wc_get_checkout_url());
        exit;
    }
}
add_action('template_redirect', 'aplicar_cupom_personalizado');

add_filter('woocommerce_get_item_data', 'exibir_link_remover_checkout', 10, 2);
function exibir_link_remover_checkout($item_data, $cart_item) {
    $product_id = $cart_item['product_id'];
    $cart_item_key = $cart_item['key'];
    $remove_link = sprintf(
        '<a href="%s" class="remove" aria-label="Remover este item" data-product_id="%s" data-cart_item_key="%s">Remover</a>',
        esc_url(wc_get_cart_remove_url($cart_item_key)),
        esc_attr($product_id),
        esc_attr($cart_item_key)
    );

    $item_data[] = array(
        'key' => 'Ação',
        'value' => $remove_link,
    );

    return $item_data;
}

add_action('template_redirect', 'redirecionar_carrinho_para_checkout');

function redirecionar_carrinho_para_checkout() {
    // Verifica se estamos na página do carrinho e se o carrinho não está vazio
    if (is_cart() && !WC()->cart->is_empty()) {
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }
}

add_action('woocommerce_cart_calculate_fees', 'desconto_por_asaas_pix');

function desconto_por_asaas_pix($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    // ID do método de pagamento ASAAS PIX
    $meio_de_pagamento_com_desconto = 'asaas-pix'; // ID do método de pagamento ASAAS PIX
    $desconto_percentual = 2; // Percentual de desconto desejado (exemplo: 5%)

    // Verifica o método de pagamento escolhido
    if (isset(WC()->session) && WC()->session->get('chosen_payment_method') == $meio_de_pagamento_com_desconto) {
        $desconto = $cart->subtotal * ($desconto_percentual / 100);
        $cart->add_fee(__('Desconto por pagamento via PIX', 'woocommerce'), -$desconto);
    }
}

add_filter('woocommerce_payment_gateways', 'reordenar_meios_pagamento');

function reordenar_meios_pagamento($gateways) {
    // Exemplo: colocando o método "asaas-pix" como o primeiro na lista
    $nova_ordem = array();

    // Especifique a nova ordem adicionando os IDs dos métodos
    $nova_ordem[] = 'asaas-pix';         // Pix da ASAAS
    $nova_ordem[] = 'asaas-credit-card'; // Cartão de Crédito ASAAS

    // Reordena conforme especificado
    $ordered_gateways = array();
    foreach ($nova_ordem as $gateway_id) {
        foreach ($gateways as $gateway) {
            if ($gateway->id == $gateway_id) {
                $ordered_gateways[] = $gateway;
            }
        }
    }

    return $ordered_gateways;
}

// Adicionar o status "Em Separação"
function adicionar_status_em_separacao() {
    register_post_status('wc-em-separacao', array(
        'label'                     => 'Em Separação',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Em Separação <span class="count">(%s)</span>', 'Em Separação <span class="count">(%s)</span>')
    ));
}
add_action('init', 'adicionar_status_em_separacao');

// Adicionar o status "Enviado"
function adicionar_status_enviado() {
    register_post_status('wc-enviado', array(
        'label'                     => 'Enviado',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Enviado <span class="count">(%s)</span>', 'Enviado <span class="count">(%s)</span>')
    ));
}
add_action('init', 'adicionar_status_enviado');

// Adicionar os novos status na lista de status do WooCommerce
function adicionar_status_personalizado_wc_lista($order_statuses) {
    $order_statuses['wc-em-separacao'] = 'Em Separação';
    $order_statuses['wc-enviado'] = 'Enviado';
    return $order_statuses;
}
add_filter('wc_order_statuses', 'adicionar_status_personalizado_wc_lista');

// Enviar e-mail para o status "Em Separação"
function email_para_status_em_separacao($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if ($order->get_status() == 'em-separacao') {
        // Enviar e-mail de mudança de status
        WC()->mailer()->get_emails()['WC_Email_Customer_Processing_Order']->trigger($order_id);
    }
}
add_action('woocommerce_order_status_em-separacao', 'email_para_status_em_separacao');

// Enviar e-mail para o status "Enviado"
function email_para_status_enviado($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if ($order->get_status() == 'enviado') {
        // Enviar e-mail de mudança de status
        WC()->mailer()->get_emails()['WC_Email_Customer_Completed_Order']->trigger($order_id);
    }
}
add_action('woocommerce_order_status_enviado', 'email_para_status_enviado');
