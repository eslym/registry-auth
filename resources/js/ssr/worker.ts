import { parentPort } from "node:worker_threads";
import { createInertiaApp, type Page } from "@eslym/svelte5-inertia";
//@ts-ignore
import { resolvePage } from "@/pages";
// @ts-ignore
import BaseLayout from "@/layouts/base.svelte";
import { render } from "svelte/server";

parentPort!.on("message", async ({ id, page }: { id: string; page: Page }) => {
	try {
		const result = (await createInertiaApp({
			page,
			resolve: resolvePage,
			setup({ App, props }) {
				return render(App, {
					props: {
						...props,
						wrap: BaseLayout as any
					},
				});
			}
		}))!;
		parentPort!.postMessage({
			id,
			success: true,
			result
		});
	} catch (err) {
		const error =
			err instanceof Error
				? {
						message: err.message,
						stack: err.stack
					}
				: {
						message: String(err)
					};
		parentPort!.postMessage({
			id,
			success: false,
			error
		});
	}
});
