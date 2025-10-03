import { createInertiaApp } from "@eslym/svelte5-inertia";
import "@/bootstrap";
import { resolvePage } from "@/pages";
import { hydrate, mount } from "svelte";
import BaseLayout from "@/layouts/base.svelte";

(async function main() {
	if (
		!import.meta.env.DEV &&
		"navigator" in window &&
		"serviceWorker" in navigator
	) {
		navigator.serviceWorker
			.register("/service-worker.js")
			.then(() => console.log("Service worker registered"));
	}
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
				hydrate(App, opts as any);
			} else {
				mount(App, opts as any);
			}
		},
		progress: false
	});
})();
