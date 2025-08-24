import { parseArgs } from "node:util";
import { Worker } from "node:worker_threads";
import { createBunServer } from "@eslym/svelte5-inertia/bun";
import type { Page } from "@eslym/svelte5-inertia";
import type { InertiaAppResponse } from "@inertiajs/core";
import { join } from "node:path";

type RenderResult = Exclude<Awaited<InertiaAppResponse>, void>;

const INVALID = Symbol("invalid");
const OUT_OF_RANGE = Symbol("out_of_range");

function ensureInt(value: string | undefined, fallback: number, min: number) {
	if (value === undefined || value === "") return fallback;
	const num = parseInt(value);
	if (!Number.isFinite(num) || !Number.isInteger(num)) {
		return INVALID;
	}
	if (num < min) {
		return OUT_OF_RANGE;
	}
	return num;
}

const { values } = parseArgs({
	options: {
		hostname: {
			type: "string",
			short: "h" // Shorthand alias
		},
		port: {
			type: "string", // Parse as string first, then convert to number
			short: "p" // Shorthand alias
		},
		unix: {
			type: "string",
			short: "u" // Shorthand alias
		},
		minWorkers: {
			type: "string",
			short: "w" // Shorthand alias
		},
		maxWorkers: {
			type: "string",
			short: "W" // Shorthand alias
		}
	},
	strict: false,
	allowPositionals: false
});

type WorkerState = {
	nexReqId: number;
	requests: Map<
		number,
		[resolve: (value: RenderResult) => void, reject: (reason?: any) => void]
	>;
	idleSince: number;
};

const idle_kill = 60 * 1000;

const states = new WeakMap<Worker, WorkerState>();

const minWorkers = ensureInt(values.minWorkers as string, 1, 0);

class WorkerError extends Error {
	private _stack?: string;

	get stack() {
		return this._stack;
	}

	constructor(message: string, stack?: string) {
		super(message);
		this.name = "WorkerError";
		this._stack = stack;
	}
}

if (typeof minWorkers !== "number") {
	if (minWorkers === INVALID) {
		throw new Error(
			"Invalid value for minWorkers. Must be a valid integer."
		);
	}
	throw new Error("minWorkers must be a valid integer greater or equal 0.");
}

const maxWorkers = ensureInt(
	values.maxWorkers as string,
	minWorkers > 4 ? minWorkers : 4,
	minWorkers
);

if (typeof maxWorkers !== "number") {
	if (maxWorkers === INVALID) {
		throw new Error(
			"Invalid value for maxWorkers. Must be a valid integer."
		);
	}
	throw new Error(
		"maxWorkers must be a valid integer greater or equal to minWorkers."
	);
}

if (maxWorkers < minWorkers) {
	throw new Error("maxWorkers must be greater than or equal to minWorkers.");
}

const workers: Worker[] = [];

type WorkerResult = { id: number } & (
	| {
			success: true;
			result: RenderResult;
	  }
	| {
			success: false;
			error: { message: string; stack?: string };
	  }
);

const worker_path = join(import.meta.dirname, "worker.js");

function newWorker(): [Worker, WorkerState] {
	const worker = new Worker(worker_path);
	workers.push(worker);
	const state: WorkerState = {
		nexReqId: 0,
		requests: new Map(),
		idleSince: Date.now()
	};
	states.set(worker, state);
	worker.on("message", (msg: WorkerResult) => {
		const state = states.get(worker);
		if (!state) return;
		const [resolve, reject] = state.requests.get(msg.id)!;
		state.requests.delete(msg.id);
		if (msg.success) {
			resolve(msg.result);
		} else {
			const err = new WorkerError(msg.error.message, msg.error.stack);
			reject(err);
		}
		if (state.requests.size === 0) {
			state.idleSince = Date.now();
		}
	});
	return [worker, state];
}

function nextWorker(): [Worker, WorkerState] {
	if (workers.length === 0) {
		return newWorker();
	}
	let check = maxWorkers as number;
	let worker = workers.shift()!;
	workers.push(worker);
	while (check--) {
		const state = states.get(worker)!;
		if (state.requests.size === 0) {
			return [worker, state];
		}
		worker = workers.shift()!;
		workers.push(worker);
	}
	if (workers.length < (maxWorkers as number)) {
		return newWorker();
	}
	const state = states.get(worker)!;
	return [worker, state];
}

setInterval(() => {
	if (workers.length <= minWorkers) return;
	const now = Date.now();
	for (const worker of workers) {
		const state = states.get(worker)!;
		if (state.requests.size === 0 && now - state.idleSince > idle_kill) {
			workers.splice(workers.indexOf(worker), 1);
			worker.terminate();
			return;
		}
	}
}, 1000);

createBunServer({
	...(values as any),
	showError: true,
	render(page: Page) {
		const [worker, state] = nextWorker();
		const id = state.nexReqId++;
		return new Promise<RenderResult>((resolve, reject) => {
			state.requests.set(id, [resolve, reject]);
			worker.postMessage({ id, page });
		});
	}
});
