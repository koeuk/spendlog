import '../css/app.css';
// vue-sonner ships its own stylesheet; without it toasts mount but stay invisible.
import 'vue-sonner/style.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { i18n } from '@/lib/i18n';

/*
 * The tab title follows the name set in Settings → Appearance, not the build-time
 * VITE_APP_NAME: that one is baked in at compile time, so renaming the app would
 * never reach the browser tab. The env var stays as the fallback for the first
 * paint, before any page props exist.
 */
let appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Keeps the title honest after an admin renames the app mid-session.
router.on('navigate', (event) => {
    appName = event.detail.page.props?.branding?.name || appName;
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // The first render happens before any 'navigate' event fires.
        appName = props.initialPage.props?.branding?.name || appName;

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
