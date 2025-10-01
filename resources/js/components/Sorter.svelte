<script lang="ts">
	import * as Select from "@/shadcn/ui/select";
	import * as Tooltip from "@/shadcn/ui/tooltip";
	import { Button } from "@/shadcn/ui/button";
	import { ArrowDownAZIcon, ArrowUpAZIcon } from "@lucide/svelte";
	import type { ClassValue } from "svelte/elements";
	import { cn } from "@/lib/utils";
	import { useInertia } from "@eslym/svelte5-inertia";
	import { noop } from "lodash-es";

	let {
		field,
		dir,
		options,
		class: kelas = undefined,
		replace = false,
		preserveState = false,
		preserveScroll = false
	}: {
		field: string;
		dir: "asc" | "desc";
		options: {
			field: string;
			label: string;
		}[];
		class?: ClassValue;
		replace?: boolean;
		preserveState?: boolean;
		preserveScroll?: boolean;
	} = $props();

	const inertia = useInertia();

	let mapping = $derived(
		Object.fromEntries(options.map((opt) => [opt.field, opt.label]))
	);

	let Icon = $derived(dir === "asc" ? ArrowDownAZIcon : ArrowUpAZIcon);

	function updateSort(field: string, dir: "asc" | "desc") {
		const url = new URL(
			inertia.page.url,
			import.meta.env.SSR ? undefined : window.location.origin
		);
		url.searchParams.set("sort", `${field},${dir}`);
		url.searchParams.delete("page");
		inertia.router.get(
			url,
			{},
			{
				replace,
				preserveState,
				preserveScroll
			}
		);
	}
</script>

<div class={cn("relative", kelas)}>
	<Select.Root type="single" bind:value={() => field, noop}>
		<Tooltip.Provider>
			<Tooltip.Root>
				<Tooltip.Trigger>
					{#snippet child({ props })}
						<Select.Trigger {...props} class="w-full pr-10">
							{mapping[field]}
						</Select.Trigger>
					{/snippet}
				</Tooltip.Trigger>
				<Tooltip.Content>Sorted By: {mapping[field]}</Tooltip.Content>
			</Tooltip.Root>
		</Tooltip.Provider>
		<Select.Content>
			{#each options as opt (opt.field)}
				<Select.Item
					value={opt.field}
					onclick={() => updateSort(opt.field, dir)}
				>
					{opt.label}
				</Select.Item>
			{/each}
		</Select.Content>
	</Select.Root>
	<Tooltip.Provider>
		<Tooltip.Root>
			<Tooltip.Trigger>
				{#snippet child({ props })}
					<Button
						{...props}
						variant="ghost"
						class="absolute top-[1px] right-[1px] size-[calc(calc(var(--spacing)*9)-2px)] rounded-l-none"
						size="icon"
						onclick={() =>
							updateSort(field, dir === "asc" ? "desc" : "asc")}
					>
						<Icon />
					</Button>
				{/snippet}
			</Tooltip.Trigger>
			<Tooltip.Content>Sort Direction ({dir})</Tooltip.Content>
		</Tooltip.Root>
	</Tooltip.Provider>
</div>
