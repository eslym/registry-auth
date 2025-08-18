import { Context } from "runed";

export type Config = {
	appName: string;
	timezone: string;
};

export const Config = new Context<Config>("config");

export function configProxy(target: () => Config): Config {
	// proxy for everything, throw error on write
	return new Proxy({} as any, {
		get(_, prop: string | symbol, receiver: any) {
			return Reflect.get(target(), prop, receiver);
		},
		set(_, prop: string | symbol) {
			throw new Error(`Cannot set property ${String(prop)} on config`);
		},
		has(_, prop: string | symbol) {
			return Reflect.has(target(), prop);
		},
		ownKeys() {
			return Reflect.ownKeys(target());
		},
		getOwnPropertyDescriptor(_, prop: string | symbol) {
			return Reflect.getOwnPropertyDescriptor(target(), prop);
		},
		defineProperty(
			_,
			prop: string | symbol,
			descriptor: PropertyDescriptor
		) {
			throw new Error(`Cannot define property ${String(prop)} on config`);
		},
		deleteProperty(_, prop: string | symbol) {
			throw new Error(`Cannot delete property ${String(prop)} on config`);
		},
		apply() {
			throw new Error(`Cannot call config as a function`);
		},
		construct() {
			throw new Error(`Cannot construct config`);
		},
		getPrototypeOf() {
			return Reflect.getPrototypeOf(target());
		},
		setPrototypeOf(_, proto: object | null) {
			throw new Error(`Cannot set prototype of config to ${proto}`);
		},
		isExtensible() {
			return false;
		},
		preventExtensions() {
			throw new Error(`Cannot prevent extensions on config`);
		}
	});
}
