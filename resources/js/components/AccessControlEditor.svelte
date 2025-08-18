<script lang="ts" module>
	const levelLabels = {
		denied: "Denied",
		"pull-only": "Pull Only",
		"pull-push": "Pull & Push"
	};
</script>

<script
	lang="ts"
	generics="T extends { access_controls: Partial<Model.AccessControl>[] }"
>
	import { ScrollArea } from "@/shadcn/ui/scroll-area";
	import type { ClassValue } from "svelte/elements";
	import { Separator } from "@/shadcn/ui/separator";
	import { Button } from "@/shadcn/ui/button";
	import * as Select from "@/shadcn/ui/select";
	import {
		PlusIcon,
		ChevronUpIcon,
		ChevronDownIcon,
		MinusIcon
	} from "@lucide/svelte";
	import type { InertiaForm } from "@eslym/svelte5-inertia";
	import { Label } from "@/shadcn/ui/label";
	import { Input } from "@/shadcn/ui/input";
	import FormErrors from "@/components/FormErrors.svelte";

	const id = $props.id();

	let {
		form,
		class: kelas = undefined,
		editable = true
	}: {
		form: InertiaForm<T>;
		editable?: boolean;
		class?: ClassValue;
	} = $props();
</script>

<ScrollArea class={kelas}>
	{#each form.data.access_controls as control, index (control.id ?? control)}
		{@const first = index === 0}
		{@const last = index === form.data.access_controls.length - 1}
		<div class="flex flex-row items-stretch gap-2.5 px-2 py-4">
			{#if editable}
				<div class="grid grid-rows-2 gap-2">
					<Button
						disabled={first}
						variant="secondary"
						size="icon"
						onclick={() => {
							const temp = form.data.access_controls[index - 1];
							form.data.access_controls[index - 1] = control;
							form.data.access_controls[index] = temp;
						}}
						class="!h-auto"
					>
						<ChevronUpIcon />
					</Button>
					<Button
						disabled={last}
						variant="secondary"
						size="icon"
						onclick={() => {
							const temp = form.data.access_controls[index + 1];
							form.data.access_controls[index + 1] = control;
							form.data.access_controls[index] = temp;
						}}
						class="!h-auto"
					>
						<ChevronDownIcon />
					</Button>
				</div>
			{/if}
			<div class="grid grow gap-2">
				<div class="grid gap-0.5">
					<Label for="{id}-access-control-{index}-repo">
						Repository Pattern
					</Label>
					<Input
						id="{id}-access-control-{index}-repo"
						type="text"
						bind:value={
							() => control.repository,
							(v) => (control.repository = v?.toLowerCase())
						}
						readonly={!editable}
					/>
					<FormErrors
						errors={form.errors[
							`access_controls.${index}.repository`
						]}
					/>
				</div>
				<div class="grid gap-0.5">
					<Label for="{id}-access-control-{index}-level">
						Access Level
					</Label>
					{#if editable}
						<Select.Root
							type="single"
							bind:value={control.access_level}
						>
							<Select.Trigger
								id="{id}-access-control-{index}-level"
								class="w-full"
							>
								{levelLabels[control.access_level!]}
							</Select.Trigger>
							<Select.Content>
								{#each Object.entries(levelLabels) as [level, label]}
									<Select.Item value={level}>
										{label}
									</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					{:else}
						<Button
							id="{id}-access-control-{index}-level"
							variant="outline"
							class="w-full justify-start"
						>
							{levelLabels[control.access_level!]}
						</Button>
					{/if}
				</div>
			</div>
			{#if editable}
				<Button
					variant="secondary"
					size="icon"
					onclick={() => {
						form.data.access_controls.splice(index, 1);
					}}
					class="!h-auto"
				>
					<MinusIcon />
				</Button>
			{/if}
		</div>
		<Separator />
	{/each}
	{#if editable}
		<div class="p-4">
			<Button
				variant="secondary"
				class="w-full"
				onclick={() =>
					form.data.access_controls.push({ access_level: "denied" })}
			>
				<PlusIcon />
			</Button>
		</div>
	{/if}
</ScrollArea>
