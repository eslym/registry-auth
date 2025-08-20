<script lang="ts" module>
	import { DateFormatter } from "@internationalized/date";

	export { default as layout } from "@/layouts/dashboard.svelte";

	const numFmt = new Intl.NumberFormat("en-US");
</script>

<script lang="ts">
	import * as Card from "@/shadcn/ui/card";
	import * as Table from "@/shadcn/ui/table";
	import { Config } from "@/lib/config";
	import { buttonVariants } from "@/shadcn/ui/button";
	import Sorter from "@/components/Sorter.svelte";
	import { Input } from "@/shadcn/ui/input";
	import Paginator from "@/components/Paginator.svelte";
	import ConfirmDeleteDialog from "@/dialogs/ConfirmDeleteDialog.svelte";
	import { EyeIcon, PencilLineIcon, TrashIcon } from "@lucide/svelte";
	import { useInertia } from "@eslym/svelte5-inertia";
	import { watch } from "runed";
	import GroupSheet from "@/sheets/GroupSheet.svelte";

	const inertia = useInertia();
	const config = Config.get();

	let date = $derived(
		new DateFormatter("en-US", {
			year: "numeric",
			month: "short",
			day: "numeric",
			hour: "2-digit",
			minute: "2-digit",
			timeZone: config.timezone
		})
	);

	let {
		user,
		groups,
		view
	}: {
		user: Model.CurrentUser;
		groups: Paginated<Model.Group, { name: "string" }>;
		view: Model.Group;
	} = $props();

	let currentView = $state(view);

	let search = $state("");

	let searching = false;

	function viewUrl(id: number) {
		const url = new URL(inertia.page.url, window.location.origin);
		url.pathname = `/groups/${id}`;
		return url.toString();
	}

	function flushSearch() {
		if (searching || search === (groups.meta.filters.name ?? "")) return;
		searching = true;
		const url = new URL(inertia.page.url, window.location.origin);
		if (search) {
			url.searchParams.set("name", search);
		} else {
			url.searchParams.delete("name");
		}
		url.searchParams.delete("page");
		inertia.router.get(
			url,
			{},
			{
				replace: true,
				preserveState: true,
				preserveScroll: true,
				onFinish: () => {
					searching = false;
					flushSearch();
				}
			}
		);
	}

	watch(
		() => search,
		() => {
			const timeout = setTimeout(() => {
				flushSearch();
			}, 250);
			return () => clearTimeout(timeout);
		}
	);

	let from = $derived(
		groups.items.length ? (groups.page.current - 1) * groups.page.limit : 0
	);
	let to = $derived(from + groups.items.length);
</script>

<svelte:head>
	<title>Groups | {config.appName}</title>
</svelte:head>

<Card.Root>
	<Card.Header>
		<Card.Title><h1>Groups</h1></Card.Title>
		<Card.Description>
			Showing {from + 1} to {to} of {groups.page.total}.
		</Card.Description>
		{#if user.is_admin}
			<Card.Action>
				<GroupSheet group={{}} replace preserveState preserveScroll>
					{#snippet children({ Trigger })}
						<Trigger class={buttonVariants()}>Create</Trigger>
					{/snippet}
				</GroupSheet>
			</Card.Action>
		{/if}
	</Card.Header>
	<Card.Content>
		<div class="flex flex-row flex-wrap gap-x-2 gap-y-2">
			<Input
				placeholder="Search"
				class="w-0 min-w-64 grow"
				bind:value={search}
			/>
			<Sorter
				field={groups.meta.sort![0]}
				dir={groups.meta.sort![1]}
				options={[
					{ field: "name", label: "Name" },
					{ field: "users", label: "Users Count" },
					{ field: "created_at", label: "Created At" }
				]}
				class="ml-auto w-40"
				replace
				preserveState
				preserveScroll
			/>
		</div>
		<Table.Root class="mt-4">
			<Table.Header>
				<Table.Row>
					<Table.Head>Name</Table.Head>
					<Table.Head class="w-0 text-center">Users</Table.Head>
					<Table.Head class="w-0 text-center text-nowrap">
						Created At
					</Table.Head>
					<Table.Head class="w-0"></Table.Head>
				</Table.Row>
			</Table.Header>
			<Table.Body>
				{#each groups.items as group (group.id)}
					<Table.Row>
						<Table.Cell>
							{group.name}
						</Table.Cell>
						<Table.Cell class="w-0 text-center text-nowrap">
							{numFmt.format(group.users_count)}
						</Table.Cell>
						<Table.Cell class="w-0 text-center text-nowrap">
							{date.format(new Date(group.created_at))}
						</Table.Cell>
						<Table.Cell class="w-0">
							<a
								href={viewUrl(group.id)}
								use:inertia.link={{
									replace: true,
									preserveScroll: true
								}}
								class={buttonVariants({
									variant: "secondary",
									size: "icon"
								})}
							>
								{#if user.is_admin}
									<PencilLineIcon />
								{:else}
									<EyeIcon />
								{/if}
							</a>
							{#if user.is_admin}
								<ConfirmDeleteDialog
									url="/groups/{group.id}"
									replace
									preserveScroll
								>
									{#snippet children({ Trigger })}
										<Trigger
											class={buttonVariants({
												variant: "destructive",
												size: "icon"
											})}
										>
											<TrashIcon />
										</Trigger>
									{/snippet}
								</ConfirmDeleteDialog>
							{/if}
						</Table.Cell>
					</Table.Row>
				{:else}
					<Table.Row>
						<Table.Cell
							colspan={4}
							class="text-center h-20 text-muted-foreground"
						>
							No records.
						</Table.Cell>
					</Table.Row>
				{/each}
			</Table.Body>
		</Table.Root>
	</Card.Content>
	<Card.Footer>
		<Paginator data={groups} />
	</Card.Footer>
</Card.Root>

{#if currentView}
	<GroupSheet
		open
		group={currentView}
		editable={user.is_admin}
		onclose={() => {
			const url = new URL(inertia.page.url, window.location.origin);
			url.pathname = "/groups";
			inertia.router.replace({
				url: url.toString(),
				preserveState: true,
				preserveScroll: true,
				props(props) {
					delete props.view;
					return props;
				}
			});
		}}
		replace
		preserveState
		preserveScroll
	/>
{/if}
