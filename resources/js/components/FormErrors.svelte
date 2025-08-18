<script lang="ts">
	import type { ClassValue } from "svelte/elements";
	import { cn } from "@/lib/utils";
	import { slide } from "svelte/transition";

	let {
		errors,
		class: kelas = undefined
	}: {
		errors: string | string[] | undefined | null;
		class?: ClassValue;
	} = $props();

	let err = $derived(
		errors ? (Array.isArray(errors) ? errors : [errors]) : []
	);
</script>

{#if err.length}
	<ul
		class={cn("text-destructive text-xs", kelas)}
		in:slide={{ duration: 150 }}
	>
		{#each err as error}
			<li>{error}</li>
		{/each}
	</ul>
{/if}
