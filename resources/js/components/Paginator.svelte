<script lang="ts">
	import * as Pagination from "@/shadcn/ui/pagination";
	import { useInertia } from "@eslym/svelte5-inertia";
	import { ChevronLeftIcon, ChevronRightIcon } from "@lucide/svelte";
	import type { ClassValue } from "svelte/elements";

	const inertia = useInertia();
	let {
		data,
		class: kelas = undefined,
		...visit
	}: {
		data: Paginated<any, any>;
		class?: ClassValue;
		replace?: boolean;
		preserveState?: boolean;
		preserveScroll?: boolean;
	} = $props();

	function getPageUrl(p: number) {
		const url = new URL(
			inertia.page.url,
			import.meta.env.SSR ? undefined : window.location.href
		);
		if (p === 1) {
			url.searchParams.delete("page");
		} else {
			url.searchParams.set("page", p.toString());
		}
		return url.href;
	}
</script>

<Pagination.Root
	count={data.page.max}
	perPage={data.page.limit}
	bind:page={() => data.page.current, () => {}}
	siblingCount={2}
	class={kelas}
>
	{#snippet children({ pages, currentPage })}
		<Pagination.Content>
			<Pagination.Item>
				<Pagination.PrevButton>
					{#snippet child({ props })}
						<a
							{...props}
							href={getPageUrl(currentPage - 1)}
							use:inertia.link={visit}
							aria-disabled={currentPage <= 1}
						>
							<ChevronLeftIcon class="size-4" />
						</a>
					{/snippet}
				</Pagination.PrevButton>
			</Pagination.Item>
			{#each pages as page (page.key)}
				{#if page.type === "ellipsis"}
					<Pagination.Item>
						<Pagination.Ellipsis />
					</Pagination.Item>
				{:else}
					<Pagination.Item>
						<Pagination.Link
							{page}
							isActive={currentPage === page.value}
						>
							{#snippet child({ props })}
								<a
									{...props}
									href={getPageUrl(page.value)}
									use:inertia.link={visit}
								>
									{page.value}
								</a>
							{/snippet}
						</Pagination.Link>
					</Pagination.Item>
				{/if}
			{/each}
			<Pagination.Item>
				<Pagination.NextButton>
					{#snippet child({ props })}
						<a
							{...props}
							href={getPageUrl(currentPage + 1)}
							use:inertia.link={visit}
							aria-disabled={currentPage >= data.page.max}
						>
							<ChevronRightIcon class="size-4" />
						</a>
					{/snippet}
				</Pagination.NextButton>
			</Pagination.Item>
		</Pagination.Content>
	{/snippet}
</Pagination.Root>
