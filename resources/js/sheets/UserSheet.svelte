<script lang="ts">
	import * as Sheet from "@/shadcn/ui/sheet";
	import { onDestroy, type Snippet } from "svelte";
	import { useFormDerived, useInertia } from "@eslym/svelte5-inertia";
	import { Label } from "@/shadcn/ui/label";
	import { Input } from "@/shadcn/ui/input";
	import FormErrors from "@/components/FormErrors.svelte";
	import { Switch } from "@/shadcn/ui/switch";
	import * as Select from "@/shadcn/ui/select";
	import { ScrollArea } from "@/shadcn/ui/scroll-area";
	import { Separator } from "@/shadcn/ui/separator";
	import { Button } from "@/shadcn/ui/button";
	import { ChevronUpIcon, ChevronDownIcon, MinusIcon } from "@lucide/svelte";
	import AccessControlEditor from "@/components/AccessControlEditor.svelte";
	import LoadingButton from "@/components/LoadingButton.svelte";

	const id = $props.id();
	const inertia = useInertia();

	let {
		open = $bindable(false),
		children = undefined,
		user,
		editable = true,
		anonymous = true,
		groups,
		onclose = undefined,
		...opts
	}: {
		open?: boolean;
		children?: Snippet<[typeof Sheet]>;
		user: Partial<Model.User>;
		groups: Model.Group[];
		editable?: boolean;
		anonymous?: boolean;
		replace?: boolean;
		preserveState?: boolean;
		preserveScroll?: boolean;
		onclose?: () => void;
	} = $props();

	let groupMap = $derived(
		Object.fromEntries(groups.map((g) => [g.id, g.name]))
	);

	let selectedGroup: number | null = $state(null);

	const form = useFormDerived(() => ({
		username: user.username ?? "",
		is_admin: user.is_admin ?? false,
		groups: user.groups?.map((g) => g.id) ?? [],
		access_controls:
			user.access_controls ?? ([] as Partial<Model.AccessControl>[]),
		password: "",
		repeat_password: "",
		password_expired_at: user.password_expired_at ?? null
	}));

	let anon = $derived(anonymous && user.username === null);

	let availableGroups = $derived(
		groups.filter((g) => !form.data.groups.includes(g.id))
	);

	let action = $derived.by(() => {
		const url = new URL(inertia.page.url, window.location.origin);
		url.pathname = `/users/${user.id ?? ""}`;
		return url.toString();
	});

	onDestroy(() => (open = false));
</script>

<Sheet.Root
	bind:open={
		() => open,
		(val) => {
			if (!form.processing) open = val;
		}
	}
	onOpenChange={(val) => {
		if (val) {
			form.reset();
			form.errors = {};
			selectedGroup = null;
		} else onclose?.();
	}}
>
	{@render children?.(Sheet)}
	<Sheet.Content side="right">
		<Sheet.Header>
			<Sheet.Title>
				{#if !user.id}
					Create User
				{:else if editable}
					Edit {anon ? "Anonymous User" : "User"}
				{:else}
					View {anon ? "Anonymous User" : "User"}
				{/if}
			</Sheet.Title>
		</Sheet.Header>
		<ScrollArea class="h-0 flex-1 border-y">
			<form
				{id}
				class="grid auto-rows-min gap-4 p-4"
				method="post"
				{action}
				use:form.action={{
					...opts,
					onSuccess() {
						open = false;
						onclose?.();
					}
				}}
			>
				{#if !anon}
					<div class="grid gap-2">
						<Label for="{id}-username">Username</Label>
						<Input
							id="{id}-username"
							bind:value={form.data.username}
							disabled={editable && Boolean(user.id)}
							readonly={!editable}
						/>
						<FormErrors errors={form.errors.username} />
					</div>
				{/if}
				{#if editable && (!user.id || !anonymous)}
					<div class="mt-6 grid gap-2">
						<div class="flex items-center gap-2">
							<Label for="{id}-is-admin" class="grow"
								>Admin Account</Label
							>
							<Switch
								id="{id}-is-admin"
								bind:checked={form.data.is_admin}
								disabled={!editable}
							/>
						</div>
					</div>
					<div class="grid gap-2">
						<Label for="{id}-password">Password</Label>
						<Input
							id="{id}-password"
							type="password"
							bind:value={form.data.password}
							disabled={!editable}
							placeholder={user.id
								? "Leave empty to keep the same password"
								: ""}
						/>
						<FormErrors errors={form.errors.password} />
					</div>
					<div class="grid gap-2">
						<Label for="{id}-repeat-password">Repeat Password</Label
						>
						<Input
							id="{id}-repeat-password"
							type="password"
							bind:value={form.data.repeat_password}
							disabled={!editable}
						/>
						<FormErrors errors={form.errors.repeat_password} />
					</div>
				{/if}
				<div class="grid gap-2 not-first:mt-6">
					<Label>Groups</Label>
					{#if editable}
						<Select.Root
							type="single"
							bind:value={selectedGroup as any}
							disabled={!availableGroups.length}
						>
							<Select.Trigger class="w-full">
								<span
									class:text-muted-foreground={!selectedGroup}
								>
									{groupMap[selectedGroup ?? ""] ||
									availableGroups.length
										? "Select Group to Add"
										: "No Available Groups"}
								</span>
							</Select.Trigger>
							<Select.Content>
								{#each availableGroups as group (group.id)}
									<Select.Item value={group.id as any}>
										{group.name}
									</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					{/if}
					<ScrollArea class="h-56 rounded-md border">
						{#each form.data.groups as gid, index (gid)}
							{@const first = index === 0}
							{@const last =
								index === form.data.groups.length - 1}
							<div class="flex items-center gap-2 px-4 py-2">
								{#if editable}
									<Button
										disabled={first}
										variant="ghost"
										size="icon"
										onclick={() => {
											const temp =
												form.data.groups[index - 1];
											form.data.groups[index - 1] = gid;
											form.data.groups[index] = temp;
										}}
									>
										<ChevronUpIcon />
									</Button>
									<Button
										disabled={last}
										variant="ghost"
										size="icon"
										onclick={() => {
											const temp =
												form.data.groups[index + 1];
											form.data.groups[index + 1] = gid;
											form.data.groups[index] = temp;
										}}
									>
										<ChevronDownIcon />
									</Button>
								{/if}
								<div class="h-9">{groupMap[gid]}</div>
								{#if editable}
									<Button
										variant="ghost"
										size="icon"
										class="ml-auto"
										onclick={() => {
											form.data.groups.splice(index, 1);
										}}
									>
										<MinusIcon />
									</Button>
								{/if}
							</div>
							{#if !last}
								<Separator />
							{/if}
						{:else}
							<div
								class="flex w-full h-full items-center justify-center text-muted-foreground"
							>
								No Groups Assigned
							</div>
						{/each}
					</ScrollArea>
				</div>
				<div class="grid gap-2">
					<Label>Access Controls</Label>
					<AccessControlEditor
						{form}
						{editable}
						class="h-100 rounded-md border"
					/>
				</div>
			</form>
		</ScrollArea>
		{#if editable}
			<Sheet.Footer>
				<LoadingButton
					form={id}
					type="submit"
					loading={form.processing}
				>
					{user.id ? "Save" : "Create"}
				</LoadingButton>
			</Sheet.Footer>
		{/if}
	</Sheet.Content>
</Sheet.Root>
