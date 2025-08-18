import type { ComponentModule } from "@eslym/svelte5-inertia";

const pages = import.meta.glob("./**/*.svelte");

export async function resolvePage(name: string): Promise<ComponentModule> {
	const load = `./${name}.svelte`;
	if (!pages[load]) {
		throw new Error(`Page not found: ${name}`);
	}
	return (await pages[load]()) as any;
}
