<?php
/**
 * استابِ ‎Elementor\Plugin::$instance->frontend‎.
 *
 * جدا از ‎elementor-stub.php‎ نگه داشته شده چون *وجودِ* کلاسِ
 * ‎Elementor\Plugin‎ رفتارِ چند ویجتِ دیگر را هم عوض می‌کند (هرجا
 * ‎class_exists('\Elementor\Plugin')‎ چک می‌شود). فقط تستی که واقعاً به
 * این نیاز دارد آن را require می‌کند.
 *
 * عمداً ‎editor‎/‎preview‎/‎documents‎ ندارد: کدِ افزونه همه‌جا با
 * ‎isset(...->editor)‎ چک می‌کند، پس نبودشان یعنی همان مسیرِ «در ادیتور
 * نیستیم» که تست‌های موجود رویش حساب کرده‌اند، دست‌نخورده می‌ماند.
 */

namespace Elementor;

if (!class_exists('\Elementor\Plugin')) {

    /** فرانت‌اندِ ساختگی که فقط می‌شمارد چند بار صدا زده شده */
    class Zig_Stub_Frontend {

        public int $builder_in_content_calls = 0;

        /**
         * همتایِ ‎Frontend::apply_builder_in_content()‎ — همان کالبکی که
         * روی ‎the_content‎ می‌نشیند و در سایتِ واقعی یک سندِ کاملِ المنتور
         * را از نو رندر می‌کند.
         */
        public function apply_builder_in_content($content) {
            $this->builder_in_content_calls++;

            return $content . '[BUILDER-RAN]';
        }

        /** جایی که ویجت‌های دیگر ممکن است صدا بزنند؛ اینجا بی‌اثر */
        public function get_builder_content_for_display($id, $with_css = false): string {
            return '';
        }

        public function get_builder_content($id, $with_css = false): string {
            return '';
        }
    }

    /**
     * کدِ افزونه هرجا ‎class_exists('\Elementor\Plugin')‎ را true ببیند،
     * وجودِ ‎documents‎/‎elements_manager‎ را هم مسلم می‌گیرد (رویِ سایتِ
     * واقعی همیشه هست). استاب باید همان‌قدر کامل باشد وگرنه تست‌هایِ دیگر
     * رویِ چیزی می‌شکنند که در تولید اصلاً مشکل نیست.
     */
    class Zig_Stub_Documents {

        public function get_current() {
            return null;
        }

        public function get($post_id) {
            return null;
        }

        public function get_doc_for_frontend($post_id) {
            return null;
        }
    }

    class Zig_Stub_Elements_Manager {

        public function create_element_instance($data) {
            return null;
        }
    }

    class Zig_Stub_Files_Manager {

        public function clear_cache(): void {
        }
    }

    final class Plugin {

        public static ?Plugin $instance = null;

        /** @var Zig_Stub_Frontend */
        public $frontend;

        /** @var Zig_Stub_Documents */
        public $documents;

        /** @var Zig_Stub_Elements_Manager */
        public $elements_manager;

        /** @var Zig_Stub_Files_Manager */
        public $files_manager;

        public function __construct() {
            $this->frontend = new Zig_Stub_Frontend();
            $this->documents = new Zig_Stub_Documents();
            $this->elements_manager = new Zig_Stub_Elements_Manager();
            $this->files_manager = new Zig_Stub_Files_Manager();
        }
    }

    Plugin::$instance = new Plugin();
}
