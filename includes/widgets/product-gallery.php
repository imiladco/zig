<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * پورتِ مستقیمِ گالریِ محصولِ الماس‌آرا.
 *
 * این ویجت طراحیِ تازه‌ای نیست — همان مارک‌آپ و همان رفتارِ جاوااسکریپتِ
 * شورت‌کدِ ‎product_gallery_modal2‎ در قالبِ الماس‌آرا است، فقط با تعویضِ
 * پیشوندها (‎product-gallery‎ → ‎zig-gallery‎، ‎Modal-Overlay‎ →
 * ‎zig-gallery-modal‎ و…) تا به‌جای شورت‌کد به این افزونه تعلق داشته باشد و
 * با نامِ کلاس‌های قالب تداخل نکند.
 *
 * عمداً هیچ کنترلِ استایلی ندارد و در قالبِ اصلی هم هیچ CSSای برایش پیدا
 * نشد — نه در ‎custom.css‎، نه در ‎single-product.css‎، نه جای دیگری.
 * چیدمانِ حداقلی در ‎zig3d-widgets.css‎ فقط برای اینکه گالری/مودال کار کند
 * اضافه شده؛ طراحیِ واقعی در دورِ اصلاحاتِ بعدی می‌آید.
 *
 *     div.zig-gallery
 *       div.zig-gallery__main                  تصویرِ شاخصِ محصول
 *       div.zig-gallery__thumbs                 حداکثر ۵ بندانگشتی از گالری
 *         div.zig-gallery__thumb[data-zig-img-id]
 *     div.zig-gallery-modal                     پنهان تا کلیکِ اولین بندانگشتی
 *       div.zig-gallery-modal__header
 *         div.zig-gallery-modal__title
 *         button.zig-gallery-modal__close
 *       div.zig-gallery-modal__body › .zig-gallery-modal__slider
 *         img.zig-gallery-modal__image[data-zig-img-id] × همهٔ گالری
 *         button.zig-gallery-modal__prev / __next
 *       div.zig-gallery-modal__footer
 *         img.zig-gallery-modal__footer-thumb[data-zig-img-id] × همهٔ گالری
 */
final class Product_Gallery extends Widget_Base {

    public function get_name(): string {
        return 'zig3d-product-gallery';
    }

    public function get_title(): string {
        return __('گالری محصول', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-gallery-grid';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['gallery', 'product', 'images', 'modal', 'woocommerce', 'گالری', 'محصول', 'تصاویر', 'مودال'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function get_script_depends(): array {
        return ['zig3d-gallery'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'product_section',
            ['label' => __('محصول', 'zig3d-widgets')]
        );

        $this->add_control(
            'product_id',
            [
                'label'       => __('شناسهٔ محصول', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'dynamic'     => ['active' => true],
                'description' => __('خالی بگذارید تا محصول جاری استفاده شود — چه در صفحهٔ محصول، چه داخل حلقهٔ فروشگاه یا قالب حلقهٔ المنتور.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void {
        if (!function_exists('wc_get_product')) {
            $this->editor_notice(__('این ویجت به ووکامرس فعال نیاز دارد.', 'zig3d-widgets'));

            return;
        }

        $settings = $this->get_settings_for_display();
        $product  = Price::resolve(absint($settings['product_id'] ?? 0));

        if (null === $product) {
            $this->editor_notice(__('محصولی پیدا نشد. شناسهٔ محصول را وارد کنید یا ویجت را داخل صفحه/قالب محصول بگذارید.', 'zig3d-widgets'));

            return;
        }

        $gallery_ids = $product->get_gallery_image_ids();
        $main_id     = $product->get_image_id();
        $has_gallery = !empty($gallery_ids);

        $this->render_gallery($main_id, $gallery_ids, $has_gallery);

        if ($has_gallery) {
            $this->render_modal($gallery_ids);
        }
    }

    /**
     * تصویرِ اصلی + حداکثر ۵ بندانگشتی — دقیقاً همان سقفی که در شورت‌کدِ
     * الماس‌آرا هم بود (‎if ($counter > 5) break;‎).
     *
     * @param int   $main_id
     * @param int[] $gallery_ids
     */
    private function render_gallery(int $main_id, array $gallery_ids, bool $has_gallery): void {
        ?>
        <div class="zig-gallery">
            <div class="zig-gallery__main">
                <?php echo wp_get_attachment_image($main_id, 'large'); ?>
            </div>
            <?php if ($has_gallery) : ?>
                <div class="zig-gallery__thumbs">
                    <?php
                    $counter = 1;

                    foreach ($gallery_ids as $attachment_id) {
                        if ($counter > 5) {
                            break;
                        }

                        printf(
                            '<div class="zig-gallery__thumb zig-gallery__thumb--%1$d" data-zig-img-id="%2$d">%3$s</div>',
                            (int) $counter,
                            (int) $attachment_id,
                            wp_get_attachment_image($attachment_id, 'thumbnail')
                        );

                        ++$counter;
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * مودالِ گالری. برخلافِ نوارِ بندانگشتی‌ها، نه صحنه و نه ردیفِ پایین
     * سقفِ ۵تایی ندارند — دقیقاً مثلِ منبعِ اصلی، هر دو همهٔ گالری را
     * می‌گیرند.
     *
     * @param int[] $gallery_ids
     */
    private function render_modal(array $gallery_ids): void {
        $last = count($gallery_ids) - 1;
        ?>
        <div class="zig-gallery-modal" style="display: none;">
            <div class="zig-gallery-modal__header">
                <div class="zig-gallery-modal__title"><?php esc_html_e('رسمی', 'zig3d-widgets'); ?></div>
                <button type="button" class="zig-gallery-modal__close" aria-label="<?php esc_attr_e('بستن', 'zig3d-widgets'); ?>">
                    <?php echo Markup::svg_icon('close'); ?>
                </button>
            </div>
            <div class="zig-gallery-modal__body">
                <div class="zig-gallery-modal__slider">
                    <?php foreach ($gallery_ids as $attachment_id) : ?>
                        <img
                            class="zig-gallery-modal__image"
                            src="<?php echo esc_url((string) wp_get_attachment_image_url($attachment_id, 'large')); ?>"
                            data-zig-img-id="<?php echo (int) $attachment_id; ?>"
                            style="display: none;"
                            alt=""
                        >
                    <?php endforeach; ?>
                    <button type="button" class="zig-gallery-modal__prev" aria-label="<?php esc_attr_e('قبلی', 'zig3d-widgets'); ?>">
                        <?php echo Markup::svg_icon('arrow'); ?>
                    </button>
                    <button type="button" class="zig-gallery-modal__next" aria-label="<?php esc_attr_e('بعدی', 'zig3d-widgets'); ?>">
                        <?php echo Markup::svg_icon('arrow', 'zig-gallery-modal__next-icon'); ?>
                    </button>
                </div>
            </div>
            <div class="zig-gallery-modal__footer">
                <?php
                foreach ($gallery_ids as $index => $attachment_id) :
                    $classes = ['zig-gallery-modal__footer-thumb'];

                    if (0 === $index) {
                        $classes[] = 'zig-gallery-modal__footer-thumb--start';
                    }

                    if ($index === $last) {
                        $classes[] = 'zig-gallery-modal__footer-thumb--end';
                    }
                    ?>
                    <img
                        class="<?php echo esc_attr(implode(' ', $classes)); ?>"
                        src="<?php echo esc_url((string) wp_get_attachment_image_url($attachment_id, 'thumbnail')); ?>"
                        data-zig-img-id="<?php echo (int) $attachment_id; ?>"
                        alt="<?php esc_attr_e('بندانگشتی', 'zig3d-widgets'); ?>"
                    >
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * پیام راهنما، فقط داخل ادیتور — در سایت هیچ‌چیز چاپ نمی‌شود.
     */
    private function editor_notice(string $message): void {
        if (!$this->is_editing()) {
            return;
        }

        printf('<div class="zig-gallery__notice">%s</div>', esc_html($message));
    }

    private function is_editing(): bool {
        return class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }
}
