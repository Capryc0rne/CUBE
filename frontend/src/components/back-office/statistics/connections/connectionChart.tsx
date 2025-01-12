"use client";
import { Bar } from 'react-chartjs-2';
import {
       Chart as ChartJS,
       CategoryScale,
       LinearScale,
       BarElement,
       Title,
       Tooltip,
       Legend,
       ChartData
} from 'chart.js';
import { useEffect, useState } from 'react';
import axios, { AxiosError, AxiosResponse } from 'axios';
import { message } from 'antd';
import dayjs from 'dayjs';
import { Spin } from "antd";
import AverageDisplay from './average';
import AverageConnection from '@/types/averageConnection';
ChartJS.register(
       CategoryScale,
       LinearScale,
       BarElement,
       Title,
       Tooltip,
       Legend,
);

interface ConnectionResponse {
       connections: {
              date: string;
              numberConnections: number;
       }[];
       average: {
              day: string;
              value: number;
       };
}

export default function ConnectionsChart({ dateRange, isPreview = false }: { dateRange?: { startDate: string, endDate: string }, isPreview?: boolean }) {
       const [data, setData] = useState<ChartData<'bar'>>({
              labels: [],
              datasets: [{
                     label: 'Nombre de connexions',
                     data: [],
                     backgroundColor: 'rgb(75, 192, 192)',
                     borderColor: 'rgba(75, 192, 192, 0.2)',
                     // borderRadius: Number.MAX_VALUE,
              }],
       });

       const [isGraphLoading, setIsGraphLoading] = useState(true);
       const [average, setAverage] = useState<AverageConnection | null>(null);

       useEffect(() => {
              let startDate = '';
              let endDate = '';

              if (isPreview) { // Calculate the date range for the last 7 days if isPreview is true
                     startDate = dayjs().subtract(6, 'days').format('DD/MM/YYYY');
                     endDate = dayjs().format('DD/MM/YYYY');
              } else if (dateRange) {
                     startDate = dateRange.startDate;
                     endDate = dateRange.endDate;
              }

              if (startDate && endDate) {
                     fetchData(startDate, endDate);
              }
       }, [dateRange, isPreview]);

       const fetchData = async (startDate: string, endDate: string) => {
              setIsGraphLoading(true);
              try {
                     const response: AxiosResponse<ConnectionResponse> = await axios({
                            method: 'get',
                            baseURL: process.env.NEXT_PUBLIC_BACKEND_API_URL,
                            url: "/stats/connections",
                            withCredentials: true,
                            params: {
                                   startDate: startDate.replace(/\//g, '-'), // Replace '/' with '-'
                                   endDate: endDate.replace(/\//g, '-'), // Replace '/' with '-'
                            },
                            responseType: 'json',
                            timeout: 10000,
                     });

                     if (response.status === 200) {
                            const connectionData = response.data.connections;

                            const labels = connectionData.map(item => item.date.replace(/-/g, '/'));
                            const datasetData = connectionData.map(item => item.numberConnections);

                            setData({
                                   labels: labels,
                                   datasets: [{
                                          label: 'Nombre de connexions',
                                          data: datasetData,
                                          backgroundColor: 'rgb(97, 121, 195)',
                                          borderColor: 'rgba(75, 192, 192, 0.2)',
                                          // borderRadius: Number.MAX_VALUE,
                                   }],
                            });
                            setAverage(response.data.average as AverageConnection);
                     } else {
                            throw new Error('Invalid status code')
                     }
              } catch (error) {
                     console.error("🚀 ~ fetchData ~ error", error);
                     const axiosError = error as AxiosError;
                     if (axiosError.response) {
                            switch (axiosError.response.status) {
                                   case 401:
                                          message.error(`Vous n'êtes pas autorisé à effectuer cette action`);
                                          break;
                                   case 403:
                                          message.error(`Vous n'êtes pas autorisé à effectuer cette action`);
                                          break;
                                   case 422:
                                          message.error('Champs manquants ou invalides');
                                          break;
                                   default:
                                          message.error(`Erreur inconnue`);
                                          break;
                            }
                     } else {
                            message.error('Erreur réseau ou serveur indisponible');
                     }
              } finally {
                     setIsGraphLoading(false);
              }
       };

       const options = {
              responsive: true,
              maintainAspectRatio: true,
              aspectRatio: 2,
       };

       return (
              <>
                     {(average && !isPreview) && (
                            <AverageDisplay average={average}></AverageDisplay>
                     )}
                     {isGraphLoading ? (
                            <div className='w-full flex flex-row justify-center mt-10'>
                                   <Spin></Spin>
                            </div>
                     ) : (
                            <Bar className='w-full' data={data} options={options} />
                     )}
              </>
       );
}
