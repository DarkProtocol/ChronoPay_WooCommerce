## ChronoPay_Payment_WooCommerce

Плагин оплаты ChronoPay к интернет магазину на основе WooCommerce.

## Установка

Скопируйте папку `woocommerce-chronopay` в директорию `wp-content/plugins/` на вашем сервере.

Зайдите в "Управление сайтом" -> "Плагины". Активируйте плагин "WooCommerce ChronoPay Gateway".

## Настройка

В управлении сайтом зайдите в "WooCommerce" -> "Настройки" -> "Оплата" -> "ChronoPay". Отметьте галочкой  "Enable ChronoPay" и заполните остальные поля..com/

SharedSec, Product ID Вам должны выдать. Payments Url (по умолчанию https://payments.chronopay.com) это url, где будет генерироваться оплата (если вы понятия не имеете, что это, то оставьте без изменений). Title - название платежной системы на странице оформления. Все поля обязательны для заполнения, иначе модуль не будет работать.
Callback url или cbUrl (на этот url приходят подтверждения оплаты) для Вашего магазина будет http://yourdomain.com/wc-api/wc_chronopay. После успешной оплаты пользователь будет перенаправлен на страницу успешного оформленного заказа в Вашей теме.

Callback URL, Callback type, Success URL, Decline URL, Payment types - устанавливаются вручную, либо в системе ChronoPay.

Time limit for payment page in minutes - максимальное время нахождения пользователя на странице оплаты (в минутах).

Expire time for order in minutes - время истечения резерва заказа (в минутах).