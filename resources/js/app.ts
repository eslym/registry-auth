import { createInertiaApp } from "@eslym/svelte5-inertia";
import "@/bootstrap";
import { resolvePage } from "@/pages";
import { hydrate, mount } from "svelte";
import BaseLayout from "@/layouts/base.svelte";

(async function main() {
	await createInertiaApp({
		resolve: resolvePage,
		setup({ el, App, props }) {
			const opts = {
				target: el!,
				props: {
					...props,
					origin: new URL(window.location.origin),
					wrap: BaseLayout
				}
			};
			if (el!.dataset.serverRendered === "true") {
				hydrate(App, opts);
			} else {
				mount(App, opts);
			}
		},
		progress: false
	});
})();
