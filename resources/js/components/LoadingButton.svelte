<script lang="ts">
	import { Button, type ButtonProps } from "@/shadcn/ui/button";
	import Loader from "@/components/Loader.svelte";
	import { cn } from "@/lib/utils";

	let {
		children,
		loading,
		disabled,
		class: kelas = undefined,
		...buttonProps
	}: ButtonProps & {
		loading?: boolean;
	} = $props();

	let shouldDisabled = $derived(loading || disabled);
</script>

<Button
	{...buttonProps}
	class={cn("relative overflow-hidden", kelas)}
	disabled={shouldDisabled}
>
	{@render children?.()}
	<span
		class:hidden={!loading}
		class="absolute inset-0 flex items-center justify-center bg-inherit"
	>
		<Loader />
	</span>
</Button>
