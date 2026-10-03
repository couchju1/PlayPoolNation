import { MongoClient } from 'mongodb';

// Cache the connection across hot reloads in dev and across invocations in prod.
const globalForMongo = globalThis as typeof globalThis & {
  _mongoClientPromise?: Promise<MongoClient>;
};

// Connect lazily so `next build` succeeds when MONGODB_URI is not set
// (e.g. Vercel preview deployments); the error surfaces at request time instead.
export function getMongoClient(): Promise<MongoClient> {
  if (!globalForMongo._mongoClientPromise) {
    const uri = process.env.MONGODB_URI;
    if (!uri) {
      throw new Error('Please define the MONGODB_URI environment variable inside .env.local');
    }
    globalForMongo._mongoClientPromise = new MongoClient(uri).connect();
  }
  return globalForMongo._mongoClientPromise;
}
